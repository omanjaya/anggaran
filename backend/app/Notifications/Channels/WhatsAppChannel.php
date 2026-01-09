<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppChannel
{
    /**
     * Send the given notification via WhatsApp.
     *
     * @param mixed $notifiable
     * @param \Illuminate\Notifications\Notification $notification
     * @return void
     */
    public function send($notifiable, Notification $notification)
    {
        // Check if WhatsApp is enabled
        if (!config('services.whatsapp.enabled', false)) {
            return;
        }

        // Get WhatsApp message from notification
        if (!method_exists($notification, 'toWhatsApp')) {
            return;
        }

        $message = $notification->toWhatsApp($notifiable);

        if (empty($message)) {
            return;
        }

        // Get recipient phone number
        $phone = $notifiable->phone ?? null;
        if (empty($phone)) {
            return;
        }

        // Format phone number (remove + and ensure it starts with country code)
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1); // Convert to Indonesian format
        }

        try {
            $this->sendMessage($phone, $message);
        } catch (\Exception $e) {
            Log::error('Failed to send WhatsApp notification: ' . $e->getMessage());
        }
    }

    /**
     * Send WhatsApp message via provider API.
     */
    protected function sendMessage(string $phone, string $message): void
    {
        $provider = config('services.whatsapp.provider', 'fonnte');

        match($provider) {
            'fonnte' => $this->sendViaFonnte($phone, $message),
            'wablas' => $this->sendViaWablas($phone, $message),
            'waapi' => $this->sendViaWaApi($phone, $message),
            default => Log::warning("Unknown WhatsApp provider: {$provider}"),
        };
    }

    /**
     * Send via Fonnte API
     * https://fonnte.com/
     */
    protected function sendViaFonnte(string $phone, string $message): void
    {
        $token = config('services.whatsapp.fonnte_token');

        if (empty($token)) {
            Log::warning('Fonnte token not configured');
            return;
        }

        $response = Http::withHeaders([
            'Authorization' => $token,
        ])->post('https://api.fonnte.com/send', [
            'target' => $phone,
            'message' => $message,
        ]);

        if (!$response->successful()) {
            Log::error('Fonnte API error: ' . $response->body());
        }
    }

    /**
     * Send via Wablas API
     * https://wablas.com/
     */
    protected function sendViaWablas(string $phone, string $message): void
    {
        $token = config('services.whatsapp.wablas_token');
        $domain = config('services.whatsapp.wablas_domain', 'jogja.wablas.com');

        if (empty($token)) {
            Log::warning('Wablas token not configured');
            return;
        }

        $response = Http::withHeaders([
            'Authorization' => $token,
        ])->post("https://{$domain}/api/send-message", [
            'phone' => $phone,
            'message' => $message,
        ]);

        if (!$response->successful()) {
            Log::error('Wablas API error: ' . $response->body());
        }
    }

    /**
     * Send via WhatsApp Business API (Meta)
     */
    protected function sendViaWaApi(string $phone, string $message): void
    {
        $token = config('services.whatsapp.wa_token');
        $phoneId = config('services.whatsapp.wa_phone_id');

        if (empty($token) || empty($phoneId)) {
            Log::warning('WhatsApp Business API not configured');
            return;
        }

        $response = Http::withToken($token)
            ->post("https://graph.facebook.com/v18.0/{$phoneId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => 'text',
                'text' => [
                    'body' => $message,
                ],
            ]);

        if (!$response->successful()) {
            Log::error('WhatsApp Business API error: ' . $response->body());
        }
    }
}
