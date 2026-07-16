<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Base\BaseController;
use App\Http\Controllers\Api\Calendar\CalendarController;
use App\Http\Controllers\Api\Community\LikeController;
use App\Http\Controllers\Api\Community\MentionSuggestionsController;
use App\Http\Controllers\Api\Community\PostController;
use App\Http\Controllers\Api\Community\ReplyController;
use App\Http\Controllers\Api\Contact\ContactController;
use App\Http\Controllers\Api\Event\EventController;
use App\Http\Controllers\Api\Event\EventDeletionController;
use App\Http\Controllers\Api\Event\EventFeedbackController;
use App\Http\Controllers\Api\Event\EventFeedbackLikeController;
use App\Http\Controllers\Api\Event\EventRegistrationController;
use App\Http\Controllers\Api\Event\EventTimeSlotController;
use App\Http\Controllers\Api\Faq\FaqController;
use App\Http\Controllers\Api\Notification\NotificationController;
use App\Http\Controllers\Api\Opportunity\LearnServeOpportunityController;
use App\Http\Controllers\Api\Opportunity\LearnServeRegistrationController;
use App\Http\Controllers\Api\Opportunity\LearnServeTimeSlotController;
use App\Http\Controllers\Api\Opportunity\OpportunityDeletionController;
use App\Http\Controllers\Api\Opportunity\OpportunityFeedbackController;
use App\Http\Controllers\Api\Opportunity\OpportunityMediaController;
use App\Http\Controllers\Api\Opportunity\ScanPermissionController;
use App\Http\Controllers\Api\Opportunity\VolunteerAttendanceController;
use App\Http\Controllers\Api\Opportunity\VolunteerOpportunityController;
use App\Http\Controllers\Api\Opportunity\VolunteerOpportunityRegistrationController;
use App\Http\Controllers\Api\Opportunity\VolunteerOpportunityRoleController;
use App\Http\Controllers\Api\Opportunity\VolunteerOpportunityTeamController;
use App\Http\Controllers\Api\Organization\OrganizationProfileController;
use App\Http\Controllers\Api\Sponsor\SponsorController;
use App\Http\Controllers\Api\Volunteer\VolunteerProfileController;
use App\Http\Controllers\Api\Volunteer\VolunteerStatisticsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — paths match Django (fursa_backend) under /api/
|--------------------------------------------------------------------------
*/

// Auth — public
Route::post('register/', [AuthController::class, 'register']);
Route::post('login/', [AuthController::class, 'login']);
Route::post('forgot-password/', [AuthController::class, 'forgotPassword']);
Route::post('change-password/', [AuthController::class, 'changePassword']);
Route::post('verify_otp_or_token/', [AuthController::class, 'verifyOtpOrToken']);
Route::post('resend_otp_or_token/', [AuthController::class, 'resendOtpOrToken']);
Route::post('check-user/', [AuthController::class, 'checkUser']);
Route::post('social-auth/', [AuthController::class, 'socialAuth']);
Route::post('linkedin/callback/', [AuthController::class, 'linkedinCallback']);
Route::get('public-profile/{user_id}/', [AuthController::class, 'publicProfile']);

// Base — public
Route::get('choices/{choice_type}/', [BaseController::class, 'choices']);
Route::get('banner-images/', [BaseController::class, 'bannerImages']);
Route::get('proxy-image/', [BaseController::class, 'proxyImage']);
Route::options('proxy-image/', [BaseController::class, 'proxyImage']);
Route::get('faqs/', [FaqController::class, 'index']);

// Organization — public (Django AllowAny)
Route::get('all-profiles/', [OrganizationProfileController::class, 'allProfiles']);

// Auth — protected
Route::middleware('auth:api')->group(function () {
    Route::get('account/', [AuthController::class, 'account']);
    Route::match(['put', 'patch'], 'account/', [AuthController::class, 'updateAccount']);
    Route::get('check-license-requirement/', [BaseController::class, 'checkLicenseRequirement']);

    Route::get('volunteer-profile/', [VolunteerProfileController::class, 'show']);
    Route::match(['put', 'patch'], 'volunteer-profile/', [VolunteerProfileController::class, 'update']);
    Route::get('all-volunteers/', [VolunteerProfileController::class, 'allVolunteers']);
    Route::get('volunteer-profile/qr-code/', [VolunteerProfileController::class, 'qrCode']);

    Route::get('organization-profile/', [OrganizationProfileController::class, 'show']);
    Route::match(['put', 'patch'], 'organization-profile/', [OrganizationProfileController::class, 'update']);
    Route::put('organization-profile/documents/', [OrganizationProfileController::class, 'updateDocuments']);
    Route::get('list-organizations/', [OrganizationProfileController::class, 'listOrganizations']);
});

Route::get('verify/{uuid}/', [VolunteerProfileController::class, 'verifyByUuid']);

// Opportunity — public lists
Route::get('list-volunteer-opportunities/', [VolunteerOpportunityController::class, 'listVolunteerOpportunities']);
Route::get('opportunities/{opportunity_id}/details/', [VolunteerOpportunityController::class, 'opportunityDetails']);
Route::get('list-all-opportunities/', [VolunteerOpportunityController::class, 'listAllOpportunities']);
Route::get('list-user-opportunities/', [VolunteerOpportunityController::class, 'listUserOpportunities']);
Route::get('learn-serve-opportunities/', [LearnServeOpportunityController::class, 'index']);
Route::get('learn-serve-opportunities/{id}/', [LearnServeOpportunityController::class, 'show']);
Route::get('opportunity-feedbacks/', [OpportunityFeedbackController::class, 'index']);
Route::get('opportunity-feedbacks/{id}/', [OpportunityFeedbackController::class, 'show']);
Route::get('download-url/', [OpportunityMediaController::class, 'imageDownloadUrl']);
Route::get('certificate/preview/{registration_id}/', [OpportunityMediaController::class, 'certificatePreview']);
Route::get('download-certificate/', [OpportunityMediaController::class, 'certificateDownload']);

// Opportunity — protected
Route::middleware('auth:api')->group(function () {
    Route::get('volunteer-opportunities/', [VolunteerOpportunityController::class, 'index']);
    Route::post('volunteer-opportunities/', [VolunteerOpportunityController::class, 'store']);
    Route::get('volunteer-opportunities/{id}/', [VolunteerOpportunityController::class, 'show']);
    Route::match(['put', 'patch'], 'volunteer-opportunities/{id}/', [VolunteerOpportunityController::class, 'update']);
    Route::patch('volunteer-opportunities/{id}/update_images/', [VolunteerOpportunityController::class, 'updateImages']);
    Route::delete('volunteer-opportunities/{id}/', [VolunteerOpportunityController::class, 'destroy']);
    Route::match(['delete', 'post'], 'volunteer-opportunities/{opportunity_id}/unregister/', [VolunteerOpportunityRegistrationController::class, 'unregister']);

    Route::get('learn-serve-opportunities/my_opportunities/', [LearnServeOpportunityController::class, 'myOpportunities']);
    Route::post('learn-serve-opportunities/', [LearnServeOpportunityController::class, 'store']);
    Route::match(['put', 'patch'], 'learn-serve-opportunities/{id}/', [LearnServeOpportunityController::class, 'update']);
    Route::patch('learn-serve-opportunities/{id}/update_images/', [LearnServeOpportunityController::class, 'updateImages']);
    Route::delete('learn-serve-opportunities/{id}/', [LearnServeOpportunityController::class, 'destroy']);
    Route::match(['delete', 'post'], 'learn-serve-opportunities/{opportunity_id}/unregister/', [LearnServeRegistrationController::class, 'unregister']);

    Route::get('volunteer-opportunity-registrations/', [VolunteerOpportunityRegistrationController::class, 'index']);
    Route::post('volunteer-opportunity-registrations/', [VolunteerOpportunityRegistrationController::class, 'store']);
    Route::patch('volunteer-opportunity-registrations/', [VolunteerOpportunityRegistrationController::class, 'updateAssignment']);
    Route::post('volunteer-opportunity-registrations/direct-register/', [VolunteerOpportunityRegistrationController::class, 'directRegister']);
    Route::post('volunteer-opportunity-registrations/direct-unregister/', [VolunteerOpportunityRegistrationController::class, 'directUnregister']);
    Route::get('volunteer-opportunity-registrations/{id}/', [VolunteerOpportunityRegistrationController::class, 'show']);
    Route::match(['put', 'patch'], 'volunteer-opportunity-registrations/{id}/', [VolunteerOpportunityRegistrationController::class, 'update']);
    Route::delete('volunteer-opportunity-registrations/{id}/', [VolunteerOpportunityRegistrationController::class, 'destroy']);

    Route::get('volunteer-opportunity-roles/', [VolunteerOpportunityRoleController::class, 'index']);
    Route::post('volunteer-opportunity-roles/', [VolunteerOpportunityRoleController::class, 'store']);
    Route::get('volunteer-opportunity-roles/{id}/', [VolunteerOpportunityRoleController::class, 'show']);
    Route::match(['put', 'patch'], 'volunteer-opportunity-roles/{id}/', [VolunteerOpportunityRoleController::class, 'update']);
    Route::delete('volunteer-opportunity-roles/{id}/', [VolunteerOpportunityRoleController::class, 'destroy']);
    Route::delete('delete-roles/{opportunity_id}/', [VolunteerOpportunityRoleController::class, 'deleteAll']);

    Route::get('volunteer-opportunity-teams/', [VolunteerOpportunityTeamController::class, 'index']);
    Route::post('volunteer-opportunity-teams/', [VolunteerOpportunityTeamController::class, 'store']);
    Route::get('volunteer-opportunity-teams/{id}/', [VolunteerOpportunityTeamController::class, 'show']);
    Route::match(['put', 'patch'], 'volunteer-opportunity-teams/{id}/', [VolunteerOpportunityTeamController::class, 'update']);
    Route::delete('volunteer-opportunity-teams/{id}/', [VolunteerOpportunityTeamController::class, 'destroy']);

    Route::get('time-slots/', [LearnServeTimeSlotController::class, 'index']);
    Route::post('time-slots/', [LearnServeTimeSlotController::class, 'store']);
    Route::get('time-slots/{id}/', [LearnServeTimeSlotController::class, 'show']);
    Route::match(['put', 'patch'], 'time-slots/{id}/', [LearnServeTimeSlotController::class, 'update']);
    Route::delete('time-slots/{id}/', [LearnServeTimeSlotController::class, 'destroy']);
    Route::delete('delete-time-slots/{opportunity_id}/', [LearnServeTimeSlotController::class, 'deleteAll']);

    Route::post('learn-serve-opportunity-registrations/', [LearnServeRegistrationController::class, 'register']);
    Route::get('learn-serve-opportunities/{opportunity_id}/registrations/', [LearnServeRegistrationController::class, 'list']);
    Route::patch('learn-serve-opportunities/{opportunity_id}/update-attendance/', [LearnServeRegistrationController::class, 'updateAttendance']);
    Route::delete('learnserve/{opportunity_id}/unregister/{user_id}/', [LearnServeRegistrationController::class, 'unregisterUser']);

    Route::post('volunteer-attendance/scan/', [VolunteerAttendanceController::class, 'scan']);
    Route::get('volunteer-attendance/history/', [VolunteerAttendanceController::class, 'history']);

    Route::post('scan-permissions/bulk-update/', [ScanPermissionController::class, 'bulkUpdate']);
    Route::get('scan-permissions/list/', [ScanPermissionController::class, 'list']);

    Route::post('opportunities/{opportunity_id}/request-deletion/', [OpportunityDeletionController::class, 'requestDeletion']);
    Route::post('admin/opportunity-deletion-action/', [OpportunityDeletionController::class, 'adminAction']);

    Route::post('opportunity-feedbacks/', [OpportunityFeedbackController::class, 'store']);
    Route::match(['put', 'patch'], 'opportunity-feedbacks/{id}/', [OpportunityFeedbackController::class, 'update']);
    Route::delete('opportunity-feedbacks/{id}/', [OpportunityFeedbackController::class, 'destroy']);
    Route::post('opportunity-feedback/{feedback_id}/like/', [OpportunityFeedbackController::class, 'like']);

    Route::delete('delete-opportunity-image/', [OpportunityMediaController::class, 'deleteImages']);
});

// Events — public list/detail
Route::get('events/', [EventController::class, 'index']);
Route::get('events/{id}/', [EventController::class, 'show']);
Route::get('event-feedback/', [EventFeedbackController::class, 'index']);
Route::get('event-feedback/{id}/', [EventFeedbackController::class, 'show']);

// Community — public list/detail
Route::get('posts/all_tags/', [PostController::class, 'allTags']);
Route::get('posts/by_tag/', [PostController::class, 'byTag']);
Route::get('posts/', [PostController::class, 'index']);
Route::get('posts/{id}/', [PostController::class, 'show']);
Route::get('replies/', [ReplyController::class, 'index']);
Route::get('replies/{id}/', [ReplyController::class, 'show']);
Route::get('mention-suggestions/', [MentionSuggestionsController::class, 'index']);

// Sponsors — public (Django ViewSet)
Route::get('sponsors/', [SponsorController::class, 'index']);
Route::get('sponsors/{id}/', [SponsorController::class, 'show']);
Route::post('sponsors/', [SponsorController::class, 'store']);
Route::match(['put', 'patch'], 'sponsors/{id}/', [SponsorController::class, 'update']);
Route::delete('sponsors/{id}/', [SponsorController::class, 'destroy']);

// Contact-us — public (Django ViewSet)
Route::get('contact-us/', [ContactController::class, 'index']);
Route::post('contact-us/', [ContactController::class, 'store']);
Route::get('contact-us/{id}/', [ContactController::class, 'show']);
Route::match(['put', 'patch'], 'contact-us/{id}/', [ContactController::class, 'update']);
Route::delete('contact-us/{id}/', [ContactController::class, 'destroy']);

// Volunteer statistics — public
Route::get('statistics/', [VolunteerStatisticsController::class, 'statistics']);
Route::get('statistics/top/', [VolunteerStatisticsController::class, 'topVolunteers']);
Route::get('user-certificates/', [VolunteerStatisticsController::class, 'userCertificates']);

Route::middleware('auth:api')->group(function () {
    // Events — protected
    Route::post('events/', [EventController::class, 'store']);
    Route::match(['put', 'patch'], 'events/{id}/', [EventController::class, 'update']);
    Route::post('events/{id}/approve/', [EventController::class, 'approve']);
    Route::post('events/{id}/register/', [EventController::class, 'register']);
    Route::post('events/{id}/reject/', [EventController::class, 'reject']);
    Route::delete('events/{id}/', [EventController::class, 'destroy']);
    Route::match(['delete', 'post'], 'events/{event_id}/unregister/', [EventRegistrationController::class, 'unregister']);
    Route::post('events/{event_id}/request-deletion/', [EventDeletionController::class, 'requestDeletion']);
    Route::post('admin/event-deletion-action/', [EventDeletionController::class, 'adminAction']);

    Route::get('event-registrations/', [EventRegistrationController::class, 'index']);
    Route::get('event-registrations/my-registrations/', [EventRegistrationController::class, 'myRegistrations']);
    Route::post('event-registrations/', [EventRegistrationController::class, 'store']);
    Route::get('event-registrations/{event_id}/', [EventRegistrationController::class, 'byEvent']);
    Route::get('event-registrations/{id}/', [EventRegistrationController::class, 'show']);
    Route::get('event-registrations/{id}/registrations/', [EventRegistrationController::class, 'eventRegistrations']);
    Route::match(['put', 'patch'], 'event-registrations/{id}/', [EventRegistrationController::class, 'update']);
    Route::delete('event-registrations/{id}/', [EventRegistrationController::class, 'destroy']);

    Route::get('event-time-slots/', [EventTimeSlotController::class, 'index']);
    Route::post('event-time-slots/', [EventTimeSlotController::class, 'store']);
    Route::get('event-time-slots/{id}/', [EventTimeSlotController::class, 'show']);
    Route::match(['put', 'patch'], 'event-time-slots/{id}/', [EventTimeSlotController::class, 'update']);
    Route::delete('event-time-slots/{id}/', [EventTimeSlotController::class, 'destroy']);

    Route::post('event-feedback/', [EventFeedbackController::class, 'store']);
    Route::match(['put', 'patch'], 'event-feedback/{id}/', [EventFeedbackController::class, 'update']);
    Route::delete('event-feedback/{id}/', [EventFeedbackController::class, 'destroy']);
    Route::get('event-feedback-like/', [EventFeedbackLikeController::class, 'index']);
    Route::post('event-feedback-like/', [EventFeedbackLikeController::class, 'store']);
    Route::get('event-feedback-like/{id}/', [EventFeedbackLikeController::class, 'show']);
    Route::match(['put', 'patch'], 'event-feedback-like/{id}/', [EventFeedbackLikeController::class, 'update']);
    Route::delete('event-feedback-like/{id}/', [EventFeedbackLikeController::class, 'destroy']);

    // Community — protected
    Route::post('posts/', [PostController::class, 'store']);
    Route::post('posts/{id}/contact-creator/', [PostController::class, 'contactCreator']);
    Route::match(['put', 'patch'], 'posts/{id}/', [PostController::class, 'update']);
    Route::delete('posts/{id}/', [PostController::class, 'destroy']);
    Route::post('replies/', [ReplyController::class, 'store']);
    Route::match(['put', 'patch'], 'replies/{id}/', [ReplyController::class, 'update']);
    Route::delete('replies/{id}/', [ReplyController::class, 'destroy']);
    Route::post('likes/toggle/', [LikeController::class, 'toggle']);

    // Notifications
    Route::get('notifications/', [NotificationController::class, 'index']);
    Route::patch('notifications/mark-read/', [NotificationController::class, 'markRead']);
    Route::delete('notifications/delete/', [NotificationController::class, 'destroy']);

    // Calendar
    Route::get('my-calendar/', [CalendarController::class, 'index']);
    Route::post('my-calendar/', [CalendarController::class, 'store']);
    Route::match(['put', 'patch'], 'my-calendar/{id}/', [CalendarController::class, 'update']);
    Route::delete('my-calendar/{id}/', [CalendarController::class, 'destroy']);
    Route::post('upload-ics/', [CalendarController::class, 'uploadIcs']);

    // Volunteer extras
    Route::get('available-volunteers/', [VolunteerStatisticsController::class, 'availableVolunteers']);
    Route::get('volunteer-detail/', [VolunteerStatisticsController::class, 'volunteerDetail']);
    Route::get('download-qr-code/', [VolunteerStatisticsController::class, 'downloadQrCode']);
    Route::post('sync-statistics/', [VolunteerStatisticsController::class, 'syncStatistics']);
});
