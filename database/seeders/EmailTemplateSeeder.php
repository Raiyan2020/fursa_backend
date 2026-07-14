<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            ['account_activation_email', 'Account Activation', 'تفعيل الحساب', 'Your OTP is: {{otp}}', 'رمز التفعيل الخاص بك: {{otp}}'],
            ['forgot_password', 'Forgot Password', 'نسيان كلمة المرور', 'Your reset OTP is: {{otp}}', 'رمز إعادة التعيين: {{otp}}'],
            ['contact_us_notification', 'New Contact Us Message', 'رسالة تواصل جديدة', 'A new contact message was received.', 'تم استلام رسالة تواصل جديدة.'],
            ['sponsor_approval_email', 'Sponsor Approved', 'تمت الموافقة على الراعي', 'Your sponsor request was approved.', 'تمت الموافقة على طلب الرعاية.'],
            ['sponsor_rejection_email', 'Sponsor Rejected', 'تم رفض الراعي', 'Your sponsor request was rejected.', 'تم رفض طلب الرعاية.'],
            ['new_sponsor_admin_notification', 'New Sponsor Request', 'طلب راعي جديد', 'A new sponsor submitted a request.', 'تم تقديم طلب راعي جديد.'],
            ['entity_profile_approval', 'Entity Approved', 'تمت الموافقة على الجهة', 'Your organization profile was approved.', 'تمت الموافقة على ملف الجهة.'],
            ['entity_profile_rejection', 'Entity Rejected', 'تم رفض الجهة', 'Your organization profile was rejected.', 'تم رفض ملف الجهة.'],
            ['volunteer_opportunity_approval_email', 'Opportunity Approved', 'تمت الموافقة على الفرصة', 'Your volunteer opportunity was approved.', 'تمت الموافقة على فرصة التطوع.'],
            ['opportunity_rejection_email', 'Opportunity Rejected', 'تم رفض الفرصة', 'Your opportunity was rejected.', 'تم رفض الفرصة.'],
            ['event_approval_email', 'Event Approved', 'تمت الموافقة على الفعالية', 'Your event was approved.', 'تمت الموافقة على الفعالية.'],
            ['event_rejection_email', 'Event Rejected', 'تم رفض الفعالية', 'Your event was rejected.', 'تم رفض الفعالية.'],
            ['volunteer_registration_confirmation', 'Registration Confirmed', 'تأكيد التسجيل', 'Your registration was confirmed.', 'تم تأكيد تسجيلك.'],
            ['user_ban_notification_by_admin', 'Account Banned', 'تم حظر الحساب', 'Your account has been banned.', 'تم حظر حسابك.'],
            ['user_unban_email', 'Account Unbanned', 'تم رفع الحظر', 'Your account has been unbanned.', 'تم رفع الحظر عن حسابك.'],
            ['admin_notification_new_entity', 'New Entity Registered', 'جهة جديدة', 'A new organization registered.', 'تم تسجيل جهة جديدة.'],
            ['volunteer_three_day_reminder', '3-Day Reminder', 'تذكير قبل 3 أيام', 'Your opportunity starts in 3 days.', 'فرصتك تبدأ خلال 3 أيام.'],
            ['volunteer_day_of_notification', 'Day-Of Reminder', 'تذكير يوم الفعالية', 'Your opportunity is today.', 'فرصتك اليوم.'],
            ['volunteer_completion_notification', 'Thanks for Completing', 'شكرًا للمشاركة', 'Thank you for completing the opportunity.', 'شكرًا لإكمال الفرصة.'],
        ];

        foreach ($templates as [$name, $subjectEn, $subjectAr, $contentEn, $contentAr]) {
            EmailTemplate::query()->firstOrCreate(
                ['name' => $name, 'language' => 'en'],
                ['subject' => $subjectEn, 'content' => $contentEn]
            );
            EmailTemplate::query()->firstOrCreate(
                ['name' => $name, 'language' => 'ar'],
                ['subject' => $subjectAr, 'content' => $contentAr]
            );
        }
    }
}
