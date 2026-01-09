<?php

namespace App\Notifications;

use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class DeviationAlertNotification extends Notification
{
    protected Collection $alerts;

    public function __construct(Collection $alerts)
    {
        $this->alerts = $alerts;
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (config('app.send_email_notifications', false) && $notifiable->email) {
            $channels[] = 'mail';
        }

        if (config('services.whatsapp.enabled', false) && $notifiable->phone) {
            $channels[] = WhatsAppChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $criticalCount = $this->alerts->where('severity', 'CRITICAL')->count();
        $highCount = $this->alerts->where('severity', 'HIGH')->count();

        $mail = (new MailMessage)
            ->subject('[SIPERA] Peringatan Deviasi Anggaran')
            ->greeting('Yth. ' . $notifiable->name)
            ->line("Terdapat {$this->alerts->count()} peringatan deviasi anggaran yang memerlukan perhatian:")
            ->line("- **Kritis:** {$criticalCount} item")
            ->line("- **Tinggi:** {$highCount} item");

        // Add top 5 critical alerts
        $topAlerts = $this->alerts->sortByDesc(function ($alert) {
            return $alert->severity === 'CRITICAL' ? 1 : 0;
        })->take(5);

        foreach ($topAlerts as $alert) {
            $mail->line("• [{$alert->severity_label}] {$alert->message}");
        }

        return $mail
            ->action('Lihat Semua Alert', url('/deviation-alerts'))
            ->line('Mohon segera tindak lanjuti peringatan ini.')
            ->salutation('Salam, SIPERA System');
    }

    public function toArray(object $notifiable): array
    {
        $criticalCount = $this->alerts->where('severity', 'CRITICAL')->count();
        $highCount = $this->alerts->where('severity', 'HIGH')->count();

        return [
            'type' => 'deviation_alert',
            'title' => 'Peringatan Deviasi Anggaran',
            'message' => "Terdapat {$this->alerts->count()} peringatan deviasi ({$criticalCount} kritis, {$highCount} tinggi)",
            'total_alerts' => $this->alerts->count(),
            'critical_count' => $criticalCount,
            'high_count' => $highCount,
            'alert_ids' => $this->alerts->pluck('id')->toArray(),
        ];
    }

    public function toWhatsApp(object $notifiable): string
    {
        $criticalCount = $this->alerts->where('severity', 'CRITICAL')->count();
        $highCount = $this->alerts->where('severity', 'HIGH')->count();

        $message = "*[SIPERA] Peringatan Deviasi Anggaran*\n\n";
        $message .= "Yth. {$notifiable->name},\n\n";
        $message .= "Terdapat *{$this->alerts->count()}* peringatan deviasi anggaran:\n";
        $message .= "• Kritis: {$criticalCount} item\n";
        $message .= "• Tinggi: {$highCount} item\n\n";

        $topAlerts = $this->alerts->sortByDesc(function ($alert) {
            return $alert->severity === 'CRITICAL' ? 1 : 0;
        })->take(3);

        foreach ($topAlerts as $alert) {
            $severity = $alert->severity === 'CRITICAL' ? '🔴' : '🟠';
            $message .= "{$severity} {$alert->message}\n";
        }

        $message .= "\nMohon segera tindak lanjuti.\n";
        $message .= "Akses SIPERA: " . url('/deviation-alerts');

        return $message;
    }
}
