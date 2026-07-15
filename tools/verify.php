<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$c = DB::table('master_choices')->whereNotNull('value_ar')->first();
echo 'AR sample: '.$c->value_en.' = '.$c->value_ar.PHP_EOL;
$u = DB::table('users')->whereNotNull('email')->first();
echo 'User sample: '.$u->email.' | type='.$u->user_type.PHP_EOL;
$vp = DB::table('volunteer_profiles')->join('users', 'users.id', '=', 'volunteer_profiles.user_id')->count();
echo 'volunteer_profiles joined users: '.$vp.PHP_EOL;
$reg = DB::table('volunteer_opportunity_registrations')
    ->join('volunteer_opportunities', 'volunteer_opportunities.id', '=', 'volunteer_opportunity_registrations.opportunity_id')
    ->count();
echo 'registrations joined opportunities: '.$reg.PHP_EOL;
