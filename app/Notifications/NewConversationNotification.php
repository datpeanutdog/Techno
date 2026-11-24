<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;

class NewConversationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $conversationId)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('A new Pasabuy conversation is waiting')
            ->view('mail.notifications.new-conversation', [
                'url' => route('conversations.show', $this->conversationId),
            ])
            ->text('mail.notifications.new-conversation-text', [
                'url' => route('conversations.show', $this->conversationId),
            ])
            ->withSymfonyMessage(function (Email $email): void {
                $logo = new DataPart(
                    new File(public_path('pasabuy-email-logo.png')),
                    'pasabuy-email-logo.png',
                    'image/png',
                );

                $email->addPart($logo->asInline()->setContentId('pasabuy-logo@pasabuy'));
            });
    }
}
