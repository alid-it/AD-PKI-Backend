<?php

namespace App\Services\Notifications\Channels;


use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use App\Models\Setting;


class MailChannel
{
    public function send(array $payload): void
    {
        // 🔥 SMTP aus DB setzen
        Config::set('mail.mailers.smtp.host', Setting::getValue('mail_host'));
        Config::set('mail.mailers.smtp.port', Setting::getValue('mail_port'));
        Config::set('mail.mailers.smtp.username', Setting::getValue('mail_username'));
        Config::set('mail.mailers.smtp.password', Setting::getValue('mail_password'));
        Config::set('mail.mailers.smtp.encryption', Setting::getValue('mail_encryption'));

        Config::set('mail.from.address', Setting::getValue('mail_from_email'));
        Config::set('mail.from.name', Setting::getValue('mail_from_name'));

        // 🔥 Empfänger
        $to = $payload['to'] ?? Setting::getValue('mail_from_email');

        if (!$to) {
            return;
        }

        $subject = $payload['title'] ?? 'Notification';
        $message = $payload['message'] ?? '';

        try {
            Mail::raw($message, function ($mail) use ($to, $subject) {
                $mail->to($to)->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::error('Mail failed', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
