<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $brandedOtp = static function (string $introEn, string $introAr, string $actionEn, string $actionAr): array {
            $en = <<<HTML
<div style="font-family:Arial,sans-serif;background:#f5f5f5;padding:24px;">
  <div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:8px;overflow:hidden;">
    <div style="background:#4b1d6a;padding:28px;text-align:center;">
      <div style="display:inline-block;background:#d4af37;color:#4b1d6a;font-weight:700;font-size:28px;padding:10px 22px;border-radius:8px;">فرصة</div>
    </div>
    <div style="padding:28px;color:#222;line-height:1.6;">
      <p>Hi {{first_name}},</p>
      <p>{$introEn}</p>
      <p>{$actionEn}</p>
      <p style="text-align:center;margin:28px 0;">
        <span style="display:inline-block;background:#2563eb;color:#fff;font-size:28px;font-weight:700;letter-spacing:4px;padding:14px 28px;border-radius:8px;">{{otp}}</span>
      </p>
      <p>This otp is valid for {{expiry_minutes}} minutes.</p>
      <p>If you didn't request this, please ignore this email.</p>
    </div>
    <div style="background:#4b1d6a;color:#fff;text-align:center;padding:16px;font-size:13px;">
      © 2025 Forsa. All rights reserved.
    </div>
  </div>
</div>
HTML;

            $ar = <<<HTML
<div style="font-family:Arial,sans-serif;background:#f5f5f5;padding:24px;direction:rtl;">
  <div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:8px;overflow:hidden;">
    <div style="background:#4b1d6a;padding:28px;text-align:center;">
      <div style="display:inline-block;background:#d4af37;color:#4b1d6a;font-weight:700;font-size:28px;padding:10px 22px;border-radius:8px;">فرصة</div>
    </div>
    <div style="padding:28px;color:#222;line-height:1.8;">
      <p>مرحبًا {{first_name}}،</p>
      <p>{$introAr}</p>
      <p>{$actionAr}</p>
      <p style="text-align:center;margin:28px 0;">
        <span style="display:inline-block;background:#2563eb;color:#fff;font-size:28px;font-weight:700;letter-spacing:4px;padding:14px 28px;border-radius:8px;">{{otp}}</span>
      </p>
      <p>هذا الرمز صالح لمدة {{expiry_minutes}} دقيقة.</p>
      <p>إذا لم تطلب ذلك، تجاهل هذا البريد.</p>
    </div>
    <div style="background:#4b1d6a;color:#fff;text-align:center;padding:16px;font-size:13px;">
      © 2025 فرصة. جميع الحقوق محفوظة.
    </div>
  </div>
</div>
HTML;

            return [$en, $ar];
        };

        [$activationEn, $activationAr] = $brandedOtp(
            'You requested to activate your account.',
            'لقد طلبت تفعيل حسابك.',
            'Use the below OTP code to activate your account:',
            'استخدم رمز OTP التالي لتفعيل حسابك:'
        );

        [$forgotEn, $forgotAr] = $brandedOtp(
            'You requested to reset your password.',
            'لقد طلبت إعادة تعيين كلمة المرور.',
            'Use the below OTP code to reset your password:',
            'استخدم رمز OTP التالي لإعادة تعيين كلمة المرور:'
        );

        $templates = [
            ['account_activation_email', 'Account Activation', 'تفعيل الحساب', $activationEn, $activationAr],
            ['forgot_password', 'Reset Your Password', 'إعادة تعيين كلمة المرور', $forgotEn, $forgotAr],
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
            ['volunteer_registration_confirmation', 'Registration confirmed: {{opportunity_title_en}}', 'تم تأكيد تسجيلك: {{opportunity_title_ar}}', 'Hi {{first_name}},<br><br>Your registration for <b>{{opportunity_title_en}}</b> is confirmed.<br><br>Start: {{start_date}} {{start_time}}<br>End: {{end_date}} {{end_time}}<br>Location: {{location}}<br>Role: {{role}}<br>Team: {{team}}<br><br>Thank you for volunteering.', 'مرحباً {{first_name}}،<br><br>تم تأكيد تسجيلك في <b>{{opportunity_title_ar}}</b>.<br><br>البداية: {{start_date}} {{start_time}}<br>النهاية: {{end_date}} {{end_time}}<br>الموقع: {{location}}<br>الدور: {{role}}<br>الفريق: {{team}}<br><br>شكراً لتطوعك.'],
            ['user_ban_notification_by_admin', 'Account Banned', 'تم حظر الحساب', 'Your account has been banned.', 'تم حظر حسابك.'],
            ['user_unban_email', 'Account Unbanned', 'تم رفع الحظر', 'Your account has been unbanned.', 'تم رفع الحظر عن حسابك.'],
            ['admin_notification_new_entity', 'New Entity Registered', 'جهة جديدة', 'A new organization registered.', 'تم تسجيل جهة جديدة.'],
            ['volunteer_three_day_reminder', '3-Day Reminder', 'تذكير قبل 3 أيام', 'Your opportunity starts in 3 days.', 'فرصتك تبدأ خلال 3 أيام.'],
            ['volunteer_day_of_notification', 'Day-Of Reminder', 'تذكير يوم الفعالية', 'Your opportunity is today.', 'فرصتك اليوم.'],
            ['volunteer_completion_notification', 'Thanks for Completing', 'شكرًا للمشاركة', 'Thank you for completing the opportunity.', 'شكرًا لإكمال الفرصة.'],
            ['opportunity_updated', 'Opportunity updated: {{opportunity_title_en}}', 'تم تحديث الفرصة: {{opportunity_title_ar}}', 'The following changed on {{opportunity_title_en}}:{{changes_html_en}}', 'التغييرات التالية على {{opportunity_title_ar}}:{{changes_html_ar}}'],
            ['check_in_window_reminder', 'Check-in window closing: {{opportunity_title_en}}', 'نافذة التحضير تُغلق قريباً: {{opportunity_title_ar}}', 'The check-in window for {{opportunity_title_en}} closes at {{closes_at}}. {{pending_check_ins}} registered volunteer(s) still have no attendance recorded.', 'نافذة التحضير للفرصة {{opportunity_title_ar}} تُغلق في {{closes_at}}. لا يزال {{pending_check_ins}} من المتطوعين المسجلين بدون تسجيل حضور.'],
            ['emergency_alert', 'Emergency: {{title_en}}', 'طوارئ: {{title_ar}}', '{{message_en}}', '{{message_ar}}'],
            ['fursa_friend_backup_notification', 'Volunteer backup needed', 'مطلوب دعم تطوعي', '{{title}} Needs {{volunteers_needed}} more volunteers in {{days_until_start}} days.', '{{title}} تحتاج إلى {{volunteers_needed}} متطوعين إضافيين خلال {{days_until_start}} أيام.'],
        ];

        foreach ($templates as [$name, $subjectEn, $subjectAr, $contentEn, $contentAr]) {
            $forceBranded = in_array($name, ['account_activation_email', 'forgot_password'], true);

            EmailTemplate::query()->updateOrCreate(
                ['name' => $name, 'language' => 'en'],
                $forceBranded
                    ? ['subject' => $subjectEn, 'content' => $contentEn]
                    : ['subject' => $subjectEn, 'content' => $contentEn]
            );

            // For non-branded templates keep first-write semantics via updateOrCreate with same content.
            EmailTemplate::query()->updateOrCreate(
                ['name' => $name, 'language' => 'ar'],
                ['subject' => $subjectAr, 'content' => $contentAr]
            );
        }
    }
}
