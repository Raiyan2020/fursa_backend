<?php

namespace App\Services\Mail;

use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/** Lightweight port of Django send_dynamic_email. */
class DynamicEmailService
{
    public static function send(string $templateName, User $user, array $context = []): bool
    {
        Log::info('DynamicEmailService::send start', [
            'template' => $templateName,
            'user_id' => $user->id,
            'email' => $user->email,
            'context_keys' => array_keys($context),
            'mailer' => config('mail.default'),
            'mail_host' => config('mail.mailers.smtp.host'),
            'mail_password_set' => filled(config('mail.mailers.smtp.password')),
        ]);

        if (! $user->email) {
            Log::warning('DynamicEmailService aborted: user has no email', [
                'template' => $templateName,
                'user_id' => $user->id,
            ]);

            return false;
        }

        $language = $user->preferred_language?->value ?? $user->preferred_language ?? 'en';

        $template = EmailTemplate::query()
            ->notDeleted()
            ->where('name', $templateName)
            ->where('language', $language)
            ->first();

        if (! $template) {
            Log::info('DynamicEmailService template not found for language, falling back to en', [
                'template' => $templateName,
                'language' => $language,
            ]);

            $template = EmailTemplate::query()
                ->notDeleted()
                ->where('name', $templateName)
                ->where('language', 'en')
                ->first();
        }

        if (! $template) {
            Log::warning('DynamicEmailService email template missing', [
                'template' => $templateName,
                'language' => $language,
                'user_id' => $user->id,
            ]);

            return false;
        }

        $replacements = [];
        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $replacements['{{ '.$key.' }}'] = (string) ($value ?? '');
                $replacements['{{'.$key.'}}'] = (string) ($value ?? '');
            }
        }

        $subject = strtr($template->subject ?? $templateName, $replacements);
        $body = strtr($template->content ?? '', $replacements);

        try {
            Mail::html($body, function ($message) use ($user, $subject) {
                $message->to($user->email)->subject($subject);
            });

            Log::info('DynamicEmailService mail sent successfully', [
                'template' => $templateName,
                'to' => $user->email,
                'subject' => $subject,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('DynamicEmailService mail failed', [
                'template' => $templateName,
                'to' => $user->email,
                'subject' => $subject,
                'error' => $e->getMessage(),
                'exception' => $e::class,
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }
}
