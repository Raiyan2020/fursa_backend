<?php

namespace App\Services\Opportunity;

use App\Enums\ApprovalStatus;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RegistrationManagementService
{
    public static function notifyStatus(User $user, Model $opportunity, ApprovalStatus $status): void
    {
        $titleEn = $opportunity->title_en ?: 'Opportunity';
        $titleAr = $opportunity->title_ar ?: $titleEn;
        $startDate = optional($opportunity->start_date)->toDateString() ?: '-';

        [$subjectEn, $subjectAr, $messageEn, $messageAr] = match ($status) {
            ApprovalStatus::APPROVED => ['Registration approved', 'تم قبول التسجيل', "Your registration for '{$titleEn}' starting {$startDate} has been approved.", "تم قبول تسجيلك في '{$titleAr}' الذي يبدأ بتاريخ {$startDate}."],
            ApprovalStatus::REJECTED => ['Registration not accepted', 'لم يتم قبول التسجيل', "Your registration for '{$titleEn}' was not accepted. You will not receive reminders for this opportunity.", "لم يتم قبول تسجيلك في '{$titleAr}'، ولن تصلك تذكيرات خاصة بهذه الفرصة."],
            ApprovalStatus::PENDING => ['Registration pending review', 'التسجيل قيد المراجعة', "Your registration for '{$titleEn}' starting {$startDate} is pending organizer review.", "تسجيلك في '{$titleAr}' الذي يبدأ بتاريخ {$startDate} قيد مراجعة الجهة المنظمة."],
        };

        NotificationService::createForUsers($subjectEn, $subjectAr, $messageEn, $messageAr, [$user->id]);
        self::sendEmail($user, $subjectAr.' - '.$subjectEn, $messageAr."\n\n".$messageEn);
    }

    public static function sendOrganizerMessage(User $user, string $subject, string $message): bool
    {
        NotificationService::createForUsers($subject, $subject, $message, $message, [$user->id]);

        return self::sendEmail($user, $subject, $message);
    }

    protected static function sendEmail(User $user, string $subject, string $message): bool
    {
        if (! $user->email) {
            return false;
        }

        try {
            $html = '<div dir="auto" style="font-family:Arial,sans-serif;line-height:1.7">'.nl2br(e($message)).'</div>';
            Mail::html($html, fn ($mail) => $mail->to($user->email)->subject($subject));

            return true;
        } catch (\Throwable $e) {
            Log::error('Registration management email failed', ['user_id' => $user->id, 'subject' => $subject, 'error' => $e->getMessage()]);

            return false;
        }
    }
}
