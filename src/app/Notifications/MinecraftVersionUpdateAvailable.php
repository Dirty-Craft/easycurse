<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class MinecraftVersionUpdateAvailable extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $modPackName,
        public string $targetMinecraftVersion,
        public string $targetSoftware,
        public int $modPackId
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
            ->subject('Minecraft Version Update Available')
            ->line("All mods in your mod pack \"{$this->modPackName}\" now have compatible versions available for {$this->targetSoftware} {$this->targetMinecraftVersion}.")
            ->line('You can now update your mod pack to this version.')
            ->action('View Mod Pack', $modPackUrl)
            ->line('Click the "Change Version" button to update your mod pack.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'mod_pack_name' => $this->modPackName,
            'target_minecraft_version' => $this->targetMinecraftVersion,
            'target_software' => $this->targetSoftware,
            'mod_pack_id' => $this->modPackId,
        ];
    }
}
