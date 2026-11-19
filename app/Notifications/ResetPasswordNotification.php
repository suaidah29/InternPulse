<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Queued so the "email me a reset link" form returns immediately instead of
 * waiting on the mail server, and so a mail outage cannot break the request.
 * Laravel's own ResetPassword notification sends synchronously.
 */
class ResetPasswordNotification extends ResetPassword implements ShouldQueue
{
    use Queueable;

    public function toMail($notifiable): MailMessage
    {
        $minutes = (int) config(
            'auth.passwords.'.config('auth.defaults.passwords').'.expire',
            60
        );

        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject('Reset your InternPulse password / Set semula kata laluan anda')
            ->greeting('InternPulse')
            ->line('We received a request to reset the password for your InternPulse account.')
            ->line('Kami menerima permintaan untuk menetapkan semula kata laluan akaun InternPulse anda.')
            ->action('Reset password / Set semula', $url)
            ->line('This link expires in '.$minutes.' minutes. / Pautan ini tamat tempoh dalam '.$minutes.' minit.')
            ->line('If you did not ask for this, you can ignore this email. / Jika anda tidak memintanya, abaikan e-mel ini.');
    }
}
