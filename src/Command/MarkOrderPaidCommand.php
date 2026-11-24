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

#[AsCommand(name: 'app:mark-order-paid', description: 'Mark an order as PAID (triggers subscriber and sends receipt)')]
class MarkOrderPaidCommand extends Command
{
    private ManagerRegistry $doctrine;
    private LoggerInterface $logger;

    public function __construct(ManagerRegistry $doctrine, LoggerInterface $logger)
    {
        parent::__construct();
        $this->doctrine = $doctrine;
        $this->logger = $logger;
    }

    protected function configure(): void
    {
        $this->addArgument('orderId', InputArgument::REQUIRED, 'Order id to mark as paid');
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

        $output->writeln('Marking order ' . $orderId . ' as PAID...');
        $order->setStatus('PAID');
        $em->persist($order);
        $em->flush();

        $output->writeln('<info>Order updated — if subscriber is active it should send email and create notification.</info>');
        return Command::SUCCESS;
    }
}
