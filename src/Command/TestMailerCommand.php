<?php

namespace App\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(name: 'app:test-mailer', description: 'Send a test email using configured MAILER_DSN')]
class TestMailerCommand extends Command
{
    private MailerInterface $mailer;
    private LoggerInterface $logger;

    public function __construct(MailerInterface $mailer, LoggerInterface $logger)
    {
        parent::__construct();
        $this->mailer = $mailer;
        $this->logger = $logger;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $to = 'tasnim81056@gmail.com';
        $from = getenv('MAILER_FROM') ?: ($_ENV['MAILER_FROM'] ?? 'noreply@localhost');
        $email = (new Email())
            ->from($from)
            ->to($to)
            ->subject('Test mail from Abstract Art Gallery')
            ->text('This is a test email.');

        try {
            $this->mailer->send($email);
            $output->writeln('<info>Test email sent (no exception thrown)</info>');
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln('<error>Failed to send test email: ' . $e->getMessage() . '</error>');
            $this->logger->error('Test mailer failed', ['exception' => $e]);
            return Command::FAILURE;
        }
    }
}
