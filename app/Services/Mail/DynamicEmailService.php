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
        if (! $user->email) {
            return false;
        }

        $language = $user->preferred_language?->value ?? $user->preferred_language ?? 'en';

        $template = EmailTemplate::query()
            ->notDeleted()
            ->where('name', $templateName)
            ->where('language', $language)
            ->first();

        if (! $template) {
            $template = EmailTemplate::query()
                ->notDeleted()
                ->where('name', $templateName)
                ->where('language', 'en')
                ->first();
        }

        if (! $template) {
            Log::info("Email template missing: {$templateName}");

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

            return true;
        } catch (\Throwable $e) {
            Log::warning('Dynamic email failed: '.$e->getMessage(), [
                'template' => $templateName,
                'to' => $user->email,
            ]);

            return false;
        }
    }
}
