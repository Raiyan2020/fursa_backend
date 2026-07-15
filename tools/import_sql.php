<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$file = $argv[1] ?? null;
if (!$file || !is_file($file)) {
    fwrite(STDERR, "SQL file not found: $file\n");
    exit(1);
}

$sql = file_get_contents($file);

try {
    DB::unprepared($sql);
    echo "IMPORT OK\n";
} catch (Throwable $e) {
    fwrite(STDERR, "IMPORT FAILED: ".$e->getMessage()."\n");
    exit(2);
}

$tables = [
    'users', 'badges', 'choice_types', 'master_choices', 'master_choice_related_tags',
    'configs', 'user_role_license_requirements', 'user_type_approvals', 'email_templates',
    'faqs', 'notifications', 'user_notifications', 'expiring_tokens', 'otp_verifications',
    'master_choice_user', 'organization_profiles', 'volunteer_profiles', 'organization_documents',
    'volunteer_statistics', 'organization_statistics', 'volunteer_opportunities',
    'learn_serve_opportunities', 'master_choice_volunteer_opportunity',
    'master_choice_learn_serve_opportunity', 'opportunity_images', 'opportunity_sponsor_images',
    'volunteer_opportunity_registrations', 'volunteer_opportunity_assignments',
    'learn_serve_opportunity_registrations', 'volunteer_opportunity_attendances',
];
foreach ($tables as $t) {
    echo str_pad($t, 42).DB::table($t)->count().PHP_EOL;
}
