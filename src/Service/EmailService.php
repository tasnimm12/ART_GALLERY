<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class EmailService
{
    private MailerInterface $mailer;
    private string $from;

    public function __construct(MailerInterface $mailer, ?string $fromEmail = null)
    {
        $this->mailer = $mailer;
        // Récupère l'adresse d'expédition depuis la variable d'environnement MAILER_FROM_EMAIL
        $this->from = $fromEmail ?? getenv('MAILER_FROM_EMAIL') ?: '';
    }

    /**
     * Envoie un email HTML.
     *
     * @param string $to
     * @param string $subject
     * @param string $html
     */
    public function send(string $to, string $subject, string $html): void
    {
        $email = (new Email())
            ->from($this->from)
            ->to($to)
            ->subject($subject)
            ->html($html);

        $this->mailer->send($email);
    }
}
