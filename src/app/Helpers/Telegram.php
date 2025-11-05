<?php

use Illuminate\Support\Facades\Http;

if (!function_exists('sendTelegram')) {
    function sendTelegram(string $message): void
    {
        $token = env('TELEGRAM_BOT_TOKEN');
        $chatId = env('TELEGRAM_CHAT_ID');

        if (!$token || !$chatId) {
            \Log::warning('Telegram not configured');
            return;
        }

        Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML',
        ]);
    }
}
