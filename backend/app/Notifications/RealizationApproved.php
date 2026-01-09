<?php

namespace App\Notifications;

use App\Models\MonthlyRealization;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RealizationApproved extends Notification
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
            ->subject('[SIPERA] Realisasi Telah Disetujui')
            ->greeting('Yth. ' . $notifiable->name)
            ->line("Realisasi Anda telah disetujui:")
            ->line("**Item Anggaran:** {$budgetItem->name}")
            ->line("**Periode:** {$monthName} {$year}")
            ->line("**Jumlah:** Rp {$amount}")
            ->line("**Disetujui oleh:** " . ($this->realization->approvedBy?->name ?? 'System'))
            ->action('Lihat Detail', url('/realization'))
            ->line('Realisasi telah dikunci dan tidak dapat diubah.')
            ->salutation('Salam, SIPERA System');
    }

    public function toArray(object $notifiable): array
    {
        $budgetItem = $this->realization->monthlyPlan->budgetItem;
        $monthName = $this->getMonthName($this->realization->monthlyPlan->month);

        return [
            'type' => 'realization_approved',
            'title' => 'Realisasi Telah Disetujui',
            'message' => "Realisasi untuk '{$budgetItem->name}' bulan {$monthName} telah disetujui.",
            'realization_id' => $this->realization->id,
            'budget_item' => $budgetItem->name,
            'month' => $this->realization->monthlyPlan->month,
            'year' => $this->realization->monthlyPlan->year,
            'amount' => (float) $this->realization->realized_amount,
            'approved_by' => $this->realization->approvedBy?->name,
            'approved_at' => $this->realization->approved_at?->toISOString(),
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
