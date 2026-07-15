<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Base\BaseController;
use App\Http\Controllers\Api\Faq\FaqController;
use App\Http\Controllers\Api\Organization\OrganizationProfileController;
use App\Http\Controllers\Api\Volunteer\VolunteerProfileController;
use Illuminate\Support\Facades\Route;

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
Route::get('public-profile/{userId}/', [AuthController::class, 'publicProfile']);

// Base — public
Route::get('choices/{choiceType}/', [BaseController::class, 'choices']);
Route::get('banner-images/', [BaseController::class, 'bannerImages']);
Route::get('proxy-image/', [BaseController::class, 'proxyImage']);
Route::options('proxy-image/', [BaseController::class, 'proxyImage']);
Route::get('faqs/', [FaqController::class, 'index']);

// Auth — protected (Authorization: Bearer <token> or Token <key>)
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
