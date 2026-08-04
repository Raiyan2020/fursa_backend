<?php

namespace App\Services\Mail;

use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Support\Arr;
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

        $renderContext = self::buildContext($user, $context);
        $subject = self::render((string) ($template->subject ?? $templateName), $renderContext);
        $body = self::render((string) ($template->content ?? ''), $renderContext);

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

    /**
     * Normalize context so both Laravel (flat) and Django (nested) templates work.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public static function buildContext(User $user, array $context = []): array
    {
        $expiry = (int) ($context['expiry_time']
            ?? $context['expiry_minutes']
            ?? config('fursa.otp_or_link_expiry_time', 30));

        $firstName = (string) ($context['first_name'] ?? $user->first_name ?: 'there');
        $otp = (string) ($context['otp_code'] ?? $context['otp'] ?? '');

        $merged = array_merge([
            'first_name' => $firstName,
            'otp' => $otp,
            'otp_code' => $otp,
            'expiry_minutes' => $expiry,
            'expiry_time' => $expiry,
            'method' => (string) ($context['method'] ?? 'OTP'),
            'user' => [
                'first_name' => $firstName,
                'last_name' => (string) ($user->last_name ?? ''),
                'email' => (string) ($user->email ?? ''),
            ],
        ], $context);

        if (! isset($merged['user']) || ! is_array($merged['user'])) {
            $merged['user'] = [
                'first_name' => $firstName,
                'last_name' => (string) ($user->last_name ?? ''),
                'email' => (string) ($user->email ?? ''),
            ];
        } else {
            $merged['user'] = array_merge([
                'first_name' => $firstName,
                'last_name' => (string) ($user->last_name ?? ''),
                'email' => (string) ($user->email ?? ''),
            ], $merged['user']);
        }

        return $merged;
    }

    /**
     * Render Django-compatible email template strings stored in the DB.
     *
     * Supports:
     * - {{ var }}, {{ nested.path }}, {{ var|lower }}, {{ var|default:"x" }}
     * - {% if var == 'value' %}...{% else %}...{% endif %}
     * - {% if var %}...{% endif %}
     *
     * @param  array<string, mixed>  $context
     */
    public static function render(string $template, array $context): string
    {
        $template = self::renderIfBlocks($template, $context);

        return (string) preg_replace_callback(
            '/\{\{\s*([^}]+?)\s*\}\}/',
            static function (array $matches) use ($context): string {
                return self::resolveExpression(trim($matches[1]), $context);
            },
            $template
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function renderIfBlocks(string $template, array $context): string
    {
        $pattern = '/\{%\s*if\s+(.+?)\s*%\}(.*?)(?:\{%\s*else\s*%\}(.*?))?\{%\s*endif\s*%\}/s';

        $previous = null;
        while ($previous !== $template) {
            $previous = $template;
            $template = (string) preg_replace_callback(
                $pattern,
                static function (array $matches) use ($context): string {
                    $condition = trim($matches[1]);
                    $truthy = self::evaluateCondition($condition, $context);

                    return $truthy ? ($matches[2] ?? '') : ($matches[3] ?? '');
                },
                $template
            );
        }

        return $template;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function evaluateCondition(string $condition, array $context): bool
    {
        if (preg_match('/^(.+?)\s*(==|!=)\s*[\'"](.+?)[\'"]$/', $condition, $m)) {
            $left = self::resolveValue(trim($m[1]), $context);
            $right = $m[3];

            return $m[2] === '==' ? ((string) $left === $right) : ((string) $left !== $right);
        }

        $value = self::resolveValue($condition, $context);

        return filled($value) && $value !== false && $value !== 0 && $value !== '0';
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function resolveExpression(string $expression, array $context): string
    {
        $parts = array_map('trim', explode('|', $expression));
        $path = array_shift($parts);
        $value = self::resolveValue($path ?? '', $context);

        foreach ($parts as $filter) {
            $value = self::applyFilter($value, $filter);
        }

        if (is_bool($value)) {
            return $value ? 'True' : 'False';
        }

        if (is_scalar($value) || $value === null) {
            return (string) ($value ?? '');
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function resolveValue(string $path, array $context): mixed
    {
        if ($path === '') {
            return null;
        }

        return Arr::get($context, $path);
    }

    private static function applyFilter(mixed $value, string $filter): mixed
    {
        $filter = trim($filter);

        if ($filter === 'lower') {
            return mb_strtolower((string) ($value ?? ''));
        }

        if ($filter === 'upper') {
            return mb_strtoupper((string) ($value ?? ''));
        }

        if (preg_match('/^default\s*:\s*[\'"](.*)[\'"]$/', $filter, $m)
            || preg_match('/^default\s*=\s*[\'"](.*)[\'"]$/', $filter, $m)) {
            return filled($value) ? $value : $m[1];
        }

        return $value;
    }
}
