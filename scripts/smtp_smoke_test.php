<?php

use Illuminate\Contracts\Console\Kernel;
use App\Models\User;
use App\Services\Mail\DynamicEmailService;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['mail.mailers.smtp.timeout' => 15]);

if (isset($argv[2])) {
    config(['mail.mailers.smtp.port' => (int) $argv[2]]);
}

$recipient = $argv[1] ?? null;
if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "A valid recipient email is required.\n");
    exit(2);
}

try {
    $user = new User([
        'first_name' => 'SMTP',
        'last_name' => 'Test',
        'email' => $recipient,
        'preferred_language' => 'en',
    ]);

    $sent = DynamicEmailService::send('volunteer_registration_confirmation', $user, [
        'opportunity_title_en' => 'Fursa test volunteer opportunity',
        'opportunity_title_ar' => 'فرصة تطوعية تجريبية من فرصة',
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(6)->toDateString(),
        'start_time' => '09:00',
        'end_time' => '13:00',
        'location' => 'Kuwait City',
        'location_url' => '',
        'role' => 'Volunteer',
        'team' => '-',
    ]);

    if (! $sent) {
        throw new RuntimeException('DynamicEmailService returned false; check the Laravel log.');
    }

    echo json_encode([
        'sent' => true,
        'mailer' => config('mail.default'),
        'host' => config('mail.mailers.smtp.host'),
        'username' => config('mail.mailers.smtp.username'),
        'password_set' => filled(config('mail.mailers.smtp.password')),
        'from' => config('mail.from.address'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, json_encode([
        'sent' => false,
        'error' => $e->getMessage(),
        'exception' => $e::class,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    exit(1);
}
