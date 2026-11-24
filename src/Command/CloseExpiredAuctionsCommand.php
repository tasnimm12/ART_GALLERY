<?php

namespace App\Command;

use App\Entity\Auction;
use App\Enum\AuctionStatus;
use App\Entity\Notification;
use App\Repository\AuctionRepository;
use App\Repository\BidRepository;
use App\Repository\UserRepository;
use App\Service\AuctionOrderService;
use App\Service\AuctionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:close-expired-auctions',
    description: 'Clôture automatiquement les enchères expirées, désigne le gagnant et crée la commande de paiement.',
)]
class CloseExpiredAuctionsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AuctionRepository      $auctionRepository,
        private BidRepository          $bidRepository,
        private AuctionService         $auctionService,
        private AuctionOrderService    $auctionOrderService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Simule la clôture sans persister en base de données.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $io->title('🔨 Clôture des enchères expirées' . ($dryRun ? ' [DRY-RUN]' : ''));

        // ── 1. Fetch expired auctions ──────────────────────────────────────────
        $now     = new \DateTimeImmutable();
        $expired = $this->auctionRepository->findExpiredUnclosed();

        if (empty($expired)) {
            $io->success('Aucune enchère expirée à clôturer.');
            return Command::SUCCESS;
        }

        $io->info(sprintf('%d enchère(s) expirée(s) trouvée(s).', count($expired)));

        // ── 2. Table recap ─────────────────────────────────────────────────────
        $table = new Table($output);
        $table->setHeaders(['ID', 'Œuvre', 'Fin', 'Meilleure offre', 'Réserve', 'Gagnant', 'Statut']);
        $rows   = [];
        $closed = 0;
        $unsold = 0;

        foreach ($expired as $auction) {
            /** @var Auction $auction */
            $highestBid  = $this->bidRepository->findHighestBid($auction);
            $reservePrice = $auction->getReservePrice() ? (float) $auction->getReservePrice() : null;
            $highestAmt   = $highestBid ? (float) $highestBid->getAmount() : null;

            // Determine outcome
            $winner      = null;
            $finalPrice  = null;
            $rowStatus   = '❌ Non vendu';

            if (
                $highestBid !== null
                && ($reservePrice === null || $highestAmt >= $reservePrice)
            ) {
                $winner     = $highestBid->getUser();
                $finalPrice = $highestAmt;
                $rowStatus  = '✅ Vendu → ' . $winner->getFirstName() . ' ' . $winner->getLastName();
            } elseif ($highestBid !== null && $reservePrice !== null && $highestAmt < $reservePrice) {
                $rowStatus = '⚠️  Réserve non atteinte';
            }

            $rows[] = [
                $auction->getId(),
                mb_strimwidth($auction->getArtworkTitle(), 0, 30, '…'),
                $auction->getEndDate()->format('d/m H:i'),
                $highestAmt ? number_format($highestAmt, 2) . ' €' : '—',
                $reservePrice ? number_format($reservePrice, 2) . ' €' : '—',
                $winner ? $winner->getEmail() : '—',
                $rowStatus,
            ];

            if ($dryRun) {
                continue;
            }

            if ($auction->getArtwork() === null) {
                $auction->setStatus(AuctionStatus::CANCELLED->value);
                $auction->setClosedAt($now);
                $this->entityManager->flush();
                $unsold++;
                continue;
            }

            // ── 3. Persist outcome ─────────────────────────────────────────────
            if ($winner !== null && $finalPrice !== null) {
                // Clôture via service existant (notification AUCTION_WON + audit)
                $this->auctionService->closeAuction($auction);

                // Créer la commande de paiement pour le gagnant
                $this->auctionOrderService->createOrderFromAuction($auction, $winner, $finalPrice);

                $this->entityManager->flush();
                $closed++;
            } else {
                // Aucun gagnant valide → UNSOLD
                $auction->setStatus(AuctionStatus::ENDED->value);
                $auction->setClosedAt($now);
                if ($auction->getArtwork() !== null) {
                    $auction->getArtwork()->setStatus('AVAILABLE');
                }

                // Notification "non vendu" si des enchérisseurs ont participé
                if ($highestBid !== null) {
                    $notif = new Notification();
                    $notif->setUser($highestBid->getUser())
                        ->setType('AUCTION_UNSOLD')
                        ->setTitle('Enchère sans vente : "' . $auction->getArtworkTitle() . '"')
                        ->setMessage(sprintf(
                            'L\'enchère pour "%s" n\'a pas atteint le prix de réserve. Votre offre de %.2f € n\'a pas été retenue.',
                            $auction->getArtworkTitle(),
                            (float) $highestBid->getAmount()
                        ));
                    $this->entityManager->persist($notif);
                }

                $this->entityManager->flush();
                $unsold++;
            }
        }

        $table->setRows($rows);
        $table->render();

        if (!$dryRun) {
            $io->success(sprintf(
                'Traitement terminé : %d vendue(s), %d non vendue(s).',
                $closed,
                $unsold
            ));
        } else {
            $io->note('Mode DRY-RUN : aucune modification persistée.');
        }

        return Command::SUCCESS;
    }
}
