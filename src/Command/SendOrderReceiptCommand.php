<?php

namespace App\Command;

use App\Entity\Order;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment as TwigEnvironment;

#[AsCommand(name: 'app:send-order-receipt', description: 'Render and send order receipt email for a given order id (debug)')]
class SendOrderReceiptCommand extends Command
{
    private ManagerRegistry $doctrine;
    private MailerInterface $mailer;
    private TwigEnvironment $twig;
    private LoggerInterface $logger;

    public function __construct(ManagerRegistry $doctrine, MailerInterface $mailer, TwigEnvironment $twig, LoggerInterface $logger)
    {
        parent::__construct();
        $this->doctrine = $doctrine;
        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->logger = $logger;
    }

    protected function configure(): void
    {
        $this->addArgument('orderId', InputArgument::REQUIRED, 'Order id to send receipt for');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $orderId = (int)$input->getArgument('orderId');
        $em = $this->doctrine->getManager();
        $order = $em->getRepository(Order::class)->find($orderId);
        if (!$order) {
            $output->writeln('<error>Order not found: ' . $orderId . '</error>');
            return Command::FAILURE;
        }

        $items = [];
        foreach ($order->getItems() as $item) {
            $items[] = sprintf('%s — %s €', $item->getArtworkTitle(), number_format((float)$item->getTotalPrice(), 2, ',', ' '));
        }

        $html = $this->twig->render('emails/order_receipt.html.twig', ['order' => $order, 'items' => $items]);
        $from = getenv('MAILER_FROM') ?: ($_ENV['MAILER_FROM'] ?? 'noreply@localhost');
        $to = $order->getUser()->getEmail();

        $email = (new Email())
            ->from($from)
            ->to($to)
            ->subject('Reçu de paiement — Commande ' . $order->getOrderNumber())
            ->html($html);

        try {
            $this->logger->info('SendOrderReceiptCommand: sending email', ['orderId' => $orderId, 'to' => $to, 'from' => $from]);
            $this->mailer->send($email);
            $this->logger->info('SendOrderReceiptCommand: send succeeded', ['orderId' => $orderId, 'to' => $to]);
            $output->writeln('<info>Email sent (no exception thrown)</info>');
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->logger->error('SendOrderReceiptCommand: send failed', ['exception' => $e, 'orderId' => $orderId]);
            $output->writeln('<error>Failed to send email: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
