<?php

namespace App\Notifications;

use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * Every notification: queued, sent only after the DB transaction commits,
 * emailed to everyone and stored in the bell for real users.
 *
 * Audience decides wording, links and price visibility:
 *  - admin  → admin panel links, prices if allowed
 *  - agent  → agent portal links, prices
 *  - booker → the agent's customer: no links, NEVER Shaheen's B2B prices
 */
abstract class BaseNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public string $audience = 'agent')
    {
        $this->afterCommit(); // send only after the DB transaction commits
    }

    public function via(object $notifiable): array
    {
        return $notifiable instanceof AnonymousNotifiable ? ['mail'] : ['mail', 'database'];
    }

    abstract protected function title(): string;

    abstract protected function body(): string;

    /** Link for the button / bell item, or null. */
    protected function url(): ?string
    {
        return null;
    }

    /** bell icon tone: navy | gold | green | red | amber | blue */
    protected function tone(): string
    {
        return 'navy';
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'url' => $this->url(),
            'tone' => $this->tone(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title().' · '.$this->siteName())
            ->greeting($this->greeting($notifiable))
            ->line($this->body());

        $this->extendMail($mail, $notifiable);

        if ($this->url() && $this->audience !== 'booker') {
            $mail->action($this->actionText(), $this->url());
        }

        return $mail->salutation('Regards,  '."\n".$this->siteName());
    }

    /** Add lines/tables/attachments in subclasses. */
    protected function extendMail(MailMessage $mail, object $notifiable): void {}

    protected function actionText(): string
    {
        return 'View details';
    }

    /** Price visibility for this recipient. */
    protected function showPrices(object $notifiable): bool
    {
        if ($this->audience === 'booker') {
            return false;
        }

        return $notifiable instanceof User ? $notifiable->canViewPrices() : true;
    }

    protected function greeting(object $notifiable): string
    {
        return $notifiable instanceof User ? "Hello {$notifiable->name}," : 'Hello,';
    }

    protected function siteName(): string
    {
        return (string) app(SettingsService::class)->get('site.name', config('app.name'));
    }
}
