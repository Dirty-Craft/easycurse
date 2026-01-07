<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class ModUpdateAvailable extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $modName,
        public string $currentVersion,
        public string $newVersion,
        public string $software,
        public string $minecraftVersion,
        public int $modPackId,
        public string $modPackName
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $modPackUrl = URL::route('mod-packs.show', $this->modPackId);

        return (new MailMessage)
            ->subject('Mod Update Available')
            ->line("A new compatible version of {$this->modName} is available for {$this->software} {$this->minecraftVersion}.")
            ->line("Current version: {$this->currentVersion}")
            ->line("New version: {$this->newVersion}")
            ->line("Mod pack: {$this->modPackName}")
            ->action('View Mod Pack', $modPackUrl)
            ->line('You can update your mod pack to use the latest version.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'mod_name' => $this->modName,
            'current_version' => $this->currentVersion,
            'new_version' => $this->newVersion,
            'software' => $this->software,
            'minecraft_version' => $this->minecraftVersion,
            'mod_pack_id' => $this->modPackId,
            'mod_pack_name' => $this->modPackName,
        ];
    }
}
