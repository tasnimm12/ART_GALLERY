<?php

namespace App\EventSubscriber;

use App\Entity\Order;
use App\Entity\Notification;
use App\Entity\AuditLog;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment as TwigEnvironment;
use Psr\Log\LoggerInterface;

class OrderStatusSubscriber implements EventSubscriber
{
    private ManagerRegistry $doctrine;
    private MailerInterface $mailer;
    private TwigEnvironment $twig;
    private LoggerInterface $logger;
    private array $ordersToProcess = [];
    private bool $processingPostFlush = false;

    public function __construct(ManagerRegistry $doctrine, MailerInterface $mailer, TwigEnvironment $twig, LoggerInterface $logger)
    {
        $this->doctrine = $doctrine;
        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->logger = $logger;
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::preUpdate,
            Events::postFlush,
        ];
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Order) {
            return;
        }

        if ($args->hasChangedField('status')) {
            $old = $args->getOldValue('status');
            $new = $args->getNewValue('status');
            $orderId = $entity->getId();
            if ($new === 'PAID' && $old !== 'PAID' && $orderId !== null) {
                $this->ordersToProcess[] = $orderId;
            }
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->processingPostFlush || empty($this->ordersToProcess)) {
            return;
        }

        $this->processingPostFlush = true;
        $em = $this->doctrine->getManager();

        try {
            while ($this->ordersToProcess !== []) {
                $orderIds = array_values(array_unique($this->ordersToProcess));
                $this->ordersToProcess = [];

                foreach ($orderIds as $orderId) {
                    $order = $em->getRepository(Order::class)->find($orderId);
                    if (!$order) {
                        continue;
                    }

                    // Ensure payments are marked PAID
                    $payments = $em->getRepository(\App\Entity\Payment::class)->findBy(['order' => $order]);
                    foreach ($payments as $payment) {
                        if ($payment->getStatus() !== 'PAID') {
                            $payment->setStatus('PAID');
                            $payment->setPaidAt(new \DateTimeImmutable());
                            $em->persist($payment);
                        }
                    }

                    // Mark artworks as SOLD
                    foreach ($order->getItems() as $item) {
                        $artwork = $item->getArtwork();
                        if ($artwork) {
                            $artwork->setStatus('SOLD');
                            $em->persist($artwork);
                        }
                    }

                    // Create a user notification with receipt details
                    $items = [];
                    foreach ($order->getItems() as $item) {
                        $items[] = sprintf('%s — %s €', $item->getArtworkTitle(), number_format((float)$item->getTotalPrice(), 2, ',', ' '));
                    }

                    $message = sprintf("Votre paiement pour la commande %s a été confirmé.\n\nMontant: %s %s\n\nArticles:\n- %s\n\nMerci pour votre achat !",
                        $order->getOrderNumber(),
                        number_format((float)$order->getTotalAmount(), 2, ',', ' '),
                        $order->getCurrency(),
                        implode("\n- ", $items)
                    );

                    $notification = new Notification();
                    $notification->setUser($order->getUser())
                        ->setType('ORDER_RECEIPT')
                        ->setTitle('Reçu de paiement — Commande ' . $order->getOrderNumber())
                        ->setMessage($message);
                    $em->persist($notification);

                    // Audit log
                    $audit = new AuditLog();
                    $audit->setUser($order->getUser())
                        ->setAction('ORDER_MARKED_PAID')
                        ->setEntityType('Order')
                        ->setEntityId($order->getId())
                        ->setDetails(['orderNumber' => $order->getOrderNumber(), 'amount' => $order->getTotalAmount()]);
                    $em->persist($audit);

                    // Send the receipt email to the user
                    try {
                        $html = $this->twig->render('emails/order_receipt.html.twig', [
                            'order' => $order,
                            'items' => $items,
                        ]);

                        $from = getenv('MAILER_FROM') ?: ($_ENV['MAILER_FROM'] ?? 'noreply@localhost');

                        $email = (new Email())
                            ->from($from)
                            ->to($order->getUser()->getEmail())
                            ->subject('Reçu de paiement — Commande ' . $order->getOrderNumber())
                            ->html($html);

                        $this->logger->info('Preparing to send order receipt email', ['orderId' => $order->getId(), 'to' => $order->getUser()->getEmail()]);

                        $this->mailer->send($email);

                        $this->logger->info('Order receipt email sent (no exception)', ['orderId' => $order->getId(), 'to' => $order->getUser()->getEmail()]);
                    } catch (\Throwable $e) {
                        // Don't break the flow if email fails; log for later inspection
                        $this->logger->error('Failed to send order receipt email', ['exception' => $e, 'orderId' => $order->getId()]);
                    }
                }

                $em->flush();
            }
        } finally {
            $this->processingPostFlush = false;
        }
    }
}
