<?php

namespace App\Command;

use App\Entity\Bid;
use App\Entity\Notification;
use App\Enum\AuctionStatus;
use App\Repository\AuctionRepository;
use App\Repository\BidRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'app:notify-outbid-users',
    description: 'Envoie les notifications de surenchère manquantes aux anciens meilleurs enchérisseurs (filet de sécurité).',
)]
class NotifyOutbidUsersCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AuctionRepository      $auctionRepository,
        private BidRepository          $bidRepository,
        private MailerInterface        $mailer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'auction-id',
                null,
                InputOption::VALUE_OPTIONAL,
                'Identifiant d\'une enchère spécifique à traiter (optionnel).',
            )
            ->addOption(
                'since-minutes',
                null,
                InputOption::VALUE_OPTIONAL,
                'Traite uniquement les offres surenchéries dans les N dernières minutes (défaut: 60).',
                60
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io            = new SymfonyStyle($input, $output);
        $auctionId     = $input->getOption('auction-id');
        $sinceMinutes  = (int) $input->getOption('since-minutes');

        $io->title('📬 Notification des enchérisseurs dépassés');

        // ── 1. Fetch offres OUTBID récentes ───────────────────────────────────
        $since = new \DateTimeImmutable("-{$sinceMinutes} minutes");

        $qb = $this->entityManager->createQueryBuilder()
            ->select('b', 'u', 'a', 'art')
            ->from(Bid::class, 'b')
            ->join('b.user', 'u')
            ->join('b.auction', 'a')
            ->join('a.artwork', 'art')
            ->where('b.status = :status')
            ->andWhere('b.createdAt >= :since')
            ->andWhere('a.status != :closed')
            ->setParameter('status', 'OUTBID')
            ->setParameter('since', $since)
            ->setParameter('closed', AuctionStatus::ENDED->value);

        if ($auctionId !== null) {
            $qb->andWhere('a.id = :auctionId')
               ->setParameter('auctionId', (int) $auctionId);
        }

        /** @var Bid[] $outbidBids */
        $outbidBids = $qb->getQuery()->getResult();

        if (empty($outbidBids)) {
            $io->success('Aucun enchérisseur surenchéri à notifier dans la période.');
            return Command::SUCCESS;
        }

        $io->info(sprintf('%d offre(s) surenchérie(s) trouvée(s).', count($outbidBids)));

        $notified = 0;
        $skipped  = 0;

        // ── 2. Pour chaque offre OUTBID ───────────────────────────────────────
        foreach ($outbidBids as $bid) {
            $user    = $bid->getUser();
            $auction = $bid->getAuction();
            $artwork = $auction->getArtwork();

            // Vérifier si une notification OUTBID récente existe déjà pour cet utilisateur/enchère
            $existingNotif = $this->entityManager->createQueryBuilder()
                ->select('n')
                ->from(Notification::class, 'n')
                ->where('n.user = :user')
                ->andWhere('n.type = :type')
                ->andWhere('n.createdAt >= :since')
                ->setParameter('user', $user)
                ->setParameter('type', 'OUTBID')
                ->setParameter('since', $since)
                ->getQuery()
                ->setMaxResults(1)
                ->getOneOrNullResult();

            // Recherche dans le message si elle concerne cette enchère
            if ($existingNotif !== null && str_contains($existingNotif->getMessage(), $artwork->getTitle())) {
                $io->writeln(sprintf(
                    '  <comment>⏭  Ignoré</comment> : %s (%s) — notification déjà envoyée.',
                    $user->getEmail(),
                    $artwork->getTitle()
                ));
                $skipped++;
                continue;
            }

            // ── 3. Créer notification en base ─────────────────────────────────
            $notif = new Notification();
            $notif->setUser($user)
                ->setType('OUTBID')
                ->setTitle('🔔 Vous avez été dépassé sur "' . $artwork->getTitle() . '"')
                ->setMessage(sprintf(
                    'Votre offre de %.2f € sur l\'enchère "%s" a été surenchérie. '
                    . 'La prochaine mise minimum est de %.2f €. '
                    . 'Vous avez jusqu\'au %s pour surenchérir !',
                    (float) $bid->getAmount(),
                    $artwork->getTitle(),
                    $auction->getMinimumNextBid(),
                    $auction->getEndDate()->format('d/m/Y à H:i')
                ));

            $this->entityManager->persist($notif);

            // ── 4. Email de rappel (silencieux en dev si MAILER_DSN=null://null) ──
            try {
                $email = (new Email())
                    ->from('noreply@abstract-art-gallery.com')
                    ->to($user->getEmail())
                    ->subject('[Abstract Art Gallery] Vous avez été dépassé sur "' . $artwork->getTitle() . '"')
                    ->html($this->buildOutbidEmailHtml($user->getFirstName(), $artwork->getTitle(), $bid, $auction));

                $this->mailer->send($email);
            } catch (\Throwable $e) {
                // Email non bloquant — la notification en base suffit
                $io->writeln(sprintf(
                    '  <comment>⚠  Email non envoyé</comment> pour %s : %s',
                    $user->getEmail(),
                    $e->getMessage()
                ));
            }

            $io->writeln(sprintf(
                '  <info>✅ Notifié</info> : %s — %s',
                $user->getEmail(),
                $artwork->getTitle()
            ));

            $notified++;
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            'Terminé : %d notifié(s), %d ignoré(s) (déjà notifiés).',
            $notified,
            $skipped
        ));

        return Command::SUCCESS;
    }

    private function buildOutbidEmailHtml(string $firstName, string $artworkTitle, Bid $bid, \App\Entity\Auction $auction): string
    {
        return sprintf(
            '<!DOCTYPE html><html lang="fr"><body style="font-family:system-ui,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
            <div style="background:#0f172a;color:#fff;padding:24px 30px;border-radius:12px 12px 0 0;">
                <h1 style="margin:0;font-size:22px;">🔔 Vous avez été dépassé !</h1>
            </div>
            <div style="background:#fff;border:1px solid #e2e8f0;border-top:none;padding:24px 30px;border-radius:0 0 12px 12px;">
                <p>Bonjour <strong>%s</strong>,</p>
                <p>Votre offre de <strong>%.2f €</strong> sur <em>"%s"</em> a été surenchérie.</p>
                <table style="width:100%%;border-collapse:collapse;margin:16px 0;">
                    <tr><td style="padding:8px;color:#64748b;">Prochaine mise minimum</td>
                        <td style="padding:8px;font-weight:700;color:#4f46e5;">%.2f €</td></tr>
                    <tr style="background:#f8fafc;"><td style="padding:8px;color:#64748b;">Fin de l\'enchère</td>
                        <td style="padding:8px;font-weight:600;">%s</td></tr>
                </table>
                <a href="http://127.0.0.1:8000/encheres/%d"
                   style="display:inline-block;background:#16a34a;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:700;margin-top:8px;">
                    Surenchérir maintenant
                </a>
                <p style="margin-top:24px;font-size:13px;color:#94a3b8;">Abstract Art Gallery — Enchères d\'art contemporain</p>
            </div>
            </body></html>',
            htmlspecialchars($firstName),
            (float) $bid->getAmount(),
            htmlspecialchars($artworkTitle),
            $auction->getMinimumNextBid(),
            $auction->getEndDate()->format('d/m/Y à H:i'),
            $auction->getId()
        );
    }
}
