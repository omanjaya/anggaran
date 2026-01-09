<?php

namespace App\Notifications;

use App\Models\MonthlyRealization;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RealizationVerified extends Notification
{
    public function __construct(
        public MonthlyRealization $realization
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (config('app.send_email_notifications', false) && $notifiable->email) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $budgetItem = $this->realization->monthlyPlan->budgetItem;
        $monthName = $this->getMonthName($this->realization->monthlyPlan->month);
        $year = $this->realization->monthlyPlan->year;
        $amount = number_format($this->realization->realized_amount, 0, ',', '.');

        return (new MailMessage)
            ->subject('[SIPERA] Realisasi Menunggu Persetujuan')
            ->greeting('Yth. ' . $notifiable->name)
            ->line("Realisasi telah diverifikasi dan menunggu persetujuan:")
            ->line("**Item Anggaran:** {$budgetItem->name}")
            ->line("**Periode:** {$monthName} {$year}")
            ->line("**Jumlah:** Rp {$amount}")
            ->line("**Diverifikasi oleh:** " . ($this->realization->verifiedBy?->name ?? 'System'))
            ->action('Lihat Detail', url('/realization/approval'))
            ->line('Mohon segera melakukan persetujuan terhadap realisasi ini.')
            ->salutation('Salam, SIPERA System');
    }

    public function toArray(object $notifiable): array
    {
        $budgetItem = $this->realization->monthlyPlan->budgetItem;
        $monthName = $this->getMonthName($this->realization->monthlyPlan->month);

        return [
            'type' => 'realization_verified',
            'title' => 'Realisasi Terverifikasi Menunggu Persetujuan',
            'message' => "Realisasi untuk '{$budgetItem->name}' bulan {$monthName} telah diverifikasi dan menunggu persetujuan.",
            'realization_id' => $this->realization->id,
            'budget_item' => $budgetItem->name,
            'month' => $this->realization->monthlyPlan->month,
            'year' => $this->realization->monthlyPlan->year,
            'amount' => (float) $this->realization->realized_amount,
            'verified_by' => $this->realization->verifiedBy?->name,
            'verified_at' => $this->realization->verified_at?->toISOString(),
        ];
    }

    private function getMonthName(int $month): string
    {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret',
            4 => 'April', 5 => 'Mei', 6 => 'Juni',
            7 => 'Juli', 8 => 'Agustus', 9 => 'September',
            10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return $months[$month] ?? '';
    }
}
