<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\BadgeController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\ConfigController;
use App\Http\Controllers\Admin\EmailTemplateController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\ForbiddenWordController;
use App\Http\Controllers\Admin\FursaFriendController;
use App\Http\Controllers\Admin\HomeController;
use App\Http\Controllers\Admin\HomeSectionController;
use App\Http\Controllers\Admin\LearnServeOpportunityController;
use App\Http\Controllers\Admin\LicenseRequirementController;
use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Admin\MasterChoiceController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\OrganizationProfileController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SiteSettingController;
use App\Http\Controllers\Admin\SponsorController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserTypeApprovalController;
use App\Http\Controllers\Admin\VolunteerOpportunityController;
use App\Http\Controllers\Admin\VolunteerProfileController;
use App\Http\Controllers\Admin\WhyFursaItemController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['guest:admin', 'localization']], function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store']);
});

Route::group(['middleware' => ['auth:admin', 'localization']], function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/', [HomeController::class, 'index'])->name('home');

    // Simple CRUD resources
    Route::resource('admins', AdminController::class)->except(['show']);
    Route::resource('badges', BadgeController::class)->except(['show']);
    Route::resource('banners', BannerController::class)->except(['show']);
    Route::resource('tags', MasterChoiceController::class)->except(['show']);
    Route::resource('faqs', FaqController::class)->except(['show']);
    Route::resource('pages', PageController::class)->except(['show']);
    Route::resource('why-fursa', WhyFursaItemController::class)
        ->except(['show'])
        ->parameters(['why-fursa' => 'why_fursa']);
    Route::get('home-sections', [HomeSectionController::class, 'index'])->name('home-sections.index');
    Route::get('home-sections/{home_section}/edit', [HomeSectionController::class, 'edit'])->name('home-sections.edit');
    Route::match(['put', 'patch'], 'home-sections/{home_section}', [HomeSectionController::class, 'update'])->name('home-sections.update');
    Route::get('site-settings', [SiteSettingController::class, 'edit'])->name('site-settings.edit');
    Route::match(['put', 'patch'], 'site-settings', [SiteSettingController::class, 'update'])->name('site-settings.update');
    Route::resource('forbidden-words', ForbiddenWordController::class)
        ->except(['show'])
        ->parameters(['forbidden-words' => 'forbiddenWord']);

    // Roles — API-style paths
    Route::get('roles/', [RoleController::class, 'index'])->name('roles.index');
    Route::post('roles/', [RoleController::class, 'store'])->name('roles.store');
    Route::get('roles/create/', [RoleController::class, 'create'])->name('roles.create');
    Route::get('roles/{role}/edit/', [RoleController::class, 'edit'])->name('roles.edit');
    Route::match(['put', 'patch'], 'roles/{role}/', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('roles/{role}/', [RoleController::class, 'destroy'])->name('roles.destroy');

    // Permissions — API-style paths
    Route::get('permissions/', [PermissionController::class, 'index'])->name('permissions.index');
    Route::post('permissions/', [PermissionController::class, 'store'])->name('permissions.store');
    Route::get('permissions/create/', [PermissionController::class, 'create'])->name('permissions.create');
    Route::get('permissions/{permission}/edit/', [PermissionController::class, 'edit'])->name('permissions.edit');
    Route::match(['put', 'patch'], 'permissions/{permission}/', [PermissionController::class, 'update'])->name('permissions.update');
    Route::delete('permissions/{permission}/', [PermissionController::class, 'destroy'])->name('permissions.destroy');

    // Users
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::post('users/{user}/ban', [UserController::class, 'ban'])->name('users.ban');
    Route::post('users/{user}/unban', [UserController::class, 'unban'])->name('users.unban');

    // Volunteer profiles
    Route::get('volunteers', [VolunteerProfileController::class, 'index'])->name('volunteers.index');
    Route::get('volunteers/{volunteer}', [VolunteerProfileController::class, 'show'])->name('volunteers.show');
    Route::get('volunteers/{volunteer}/edit', [VolunteerProfileController::class, 'edit'])->name('volunteers.edit');
    Route::put('volunteers/{volunteer}', [VolunteerProfileController::class, 'update'])->name('volunteers.update');
    Route::delete('volunteers/{volunteer}', [VolunteerProfileController::class, 'destroy'])->name('volunteers.destroy');

    // Organization (Entity) profiles
    Route::get('entities', [OrganizationProfileController::class, 'index'])->name('entities.index');
    Route::get('entities/{entity}', [OrganizationProfileController::class, 'show'])->name('entities.show');
    Route::get('entities/{entity}/edit', [OrganizationProfileController::class, 'edit'])->name('entities.edit');
    Route::put('entities/{entity}', [OrganizationProfileController::class, 'update'])->name('entities.update');
    Route::delete('entities/{entity}', [OrganizationProfileController::class, 'destroy'])->name('entities.destroy');
    Route::post('entities/{entity}/approve', [OrganizationProfileController::class, 'approve'])->name('entities.approve');
    Route::post('entities/{entity}/reject', [OrganizationProfileController::class, 'reject'])->name('entities.reject');

    // Volunteer opportunities
    Route::get('volunteer-opportunities', [VolunteerOpportunityController::class, 'index'])->name('volunteer-opportunities.index');
    Route::get('volunteer-opportunities/{opportunity}', [VolunteerOpportunityController::class, 'show'])->name('volunteer-opportunities.show');
    Route::get('volunteer-opportunities/{opportunity}/edit', [VolunteerOpportunityController::class, 'edit'])->name('volunteer-opportunities.edit');
    Route::put('volunteer-opportunities/{opportunity}', [VolunteerOpportunityController::class, 'update'])->name('volunteer-opportunities.update');
    Route::delete('volunteer-opportunities/{opportunity}', [VolunteerOpportunityController::class, 'destroy'])->name('volunteer-opportunities.destroy');
    Route::post('volunteer-opportunities/{opportunity}/approve', [VolunteerOpportunityController::class, 'approve'])->name('volunteer-opportunities.approve');
    Route::post('volunteer-opportunities/{opportunity}/reject', [VolunteerOpportunityController::class, 'reject'])->name('volunteer-opportunities.reject');
    Route::post('volunteer-opportunities/{opportunity}/approve-deletion', [VolunteerOpportunityController::class, 'approveDeletion'])->name('volunteer-opportunities.approve-deletion');
    Route::post('volunteer-opportunities/{opportunity}/reject-deletion', [VolunteerOpportunityController::class, 'rejectDeletion'])->name('volunteer-opportunities.reject-deletion');

    // Learn & Serve opportunities
    Route::get('learn-serve-opportunities', [LearnServeOpportunityController::class, 'index'])->name('learn-serve-opportunities.index');
    Route::get('learn-serve-opportunities/{opportunity}', [LearnServeOpportunityController::class, 'show'])->name('learn-serve-opportunities.show');
    Route::get('learn-serve-opportunities/{opportunity}/edit', [LearnServeOpportunityController::class, 'edit'])->name('learn-serve-opportunities.edit');
    Route::put('learn-serve-opportunities/{opportunity}', [LearnServeOpportunityController::class, 'update'])->name('learn-serve-opportunities.update');
    Route::delete('learn-serve-opportunities/{opportunity}', [LearnServeOpportunityController::class, 'destroy'])->name('learn-serve-opportunities.destroy');
    Route::post('learn-serve-opportunities/{opportunity}/approve', [LearnServeOpportunityController::class, 'approve'])->name('learn-serve-opportunities.approve');
    Route::post('learn-serve-opportunities/{opportunity}/reject', [LearnServeOpportunityController::class, 'reject'])->name('learn-serve-opportunities.reject');
    Route::post('learn-serve-opportunities/{opportunity}/approve-deletion', [LearnServeOpportunityController::class, 'approveDeletion'])->name('learn-serve-opportunities.approve-deletion');
    Route::post('learn-serve-opportunities/{opportunity}/reject-deletion', [LearnServeOpportunityController::class, 'rejectDeletion'])->name('learn-serve-opportunities.reject-deletion');

    // Events
    Route::get('events', [EventController::class, 'index'])->name('events.index');
    Route::get('events/{event}', [EventController::class, 'show'])->name('events.show');
    Route::get('events/{event}/edit', [EventController::class, 'edit'])->name('events.edit');
    Route::put('events/{event}', [EventController::class, 'update'])->name('events.update');
    Route::delete('events/{event}', [EventController::class, 'destroy'])->name('events.destroy');
    Route::post('events/{event}/approve', [EventController::class, 'approve'])->name('events.approve');
    Route::post('events/{event}/reject', [EventController::class, 'reject'])->name('events.reject');
    Route::post('events/{event}/approve-deletion', [EventController::class, 'approveDeletion'])->name('events.approve-deletion');
    Route::post('events/{event}/reject-deletion', [EventController::class, 'rejectDeletion'])->name('events.reject-deletion');

    // Sponsors
    Route::get('sponsors', [SponsorController::class, 'index'])->name('sponsors.index');
    Route::get('sponsors/{sponsor}', [SponsorController::class, 'show'])->name('sponsors.show');
    Route::delete('sponsors/{sponsor}', [SponsorController::class, 'destroy'])->name('sponsors.destroy');
    Route::post('sponsors/{sponsor}/approve', [SponsorController::class, 'approve'])->name('sponsors.approve');
    Route::post('sponsors/{sponsor}/reject', [SponsorController::class, 'reject'])->name('sponsors.reject');

    // Config / general settings (singleton)
    Route::get('settings', [ConfigController::class, 'edit'])->name('settings.index');
    Route::put('settings', [ConfigController::class, 'update'])->name('settings.update');

    // License requirements
    Route::get('license-requirements', [LicenseRequirementController::class, 'index'])->name('license-requirements.index');
    Route::get('license-requirements/{requirement}/edit', [LicenseRequirementController::class, 'edit'])->name('license-requirements.edit');
    Route::put('license-requirements/{requirement}', [LicenseRequirementController::class, 'update'])->name('license-requirements.update');

    // User type approvals
    Route::get('user-type-approvals', [UserTypeApprovalController::class, 'index'])->name('user-type-approvals.index');
    Route::get('user-type-approvals/{approval}/edit', [UserTypeApprovalController::class, 'edit'])->name('user-type-approvals.edit');
    Route::put('user-type-approvals/{approval}', [UserTypeApprovalController::class, 'update'])->name('user-type-approvals.update');

    // Email templates
    Route::get('email-templates', [EmailTemplateController::class, 'index'])->name('email-templates.index');
    Route::get('email-templates/{template}/edit', [EmailTemplateController::class, 'edit'])->name('email-templates.edit');
    Route::put('email-templates/{template}', [EmailTemplateController::class, 'update'])->name('email-templates.update');

    // Forsa friends
    Route::get('fursa-friends', [FursaFriendController::class, 'index'])->name('fursa-friends.index');
    Route::get('fursa-friends/create', [FursaFriendController::class, 'create'])->name('fursa-friends.create');
    Route::post('fursa-friends', [FursaFriendController::class, 'store'])->name('fursa-friends.store');
    Route::delete('fursa-friends/{fursaFriend}', [FursaFriendController::class, 'destroy'])->name('fursa-friends.destroy');

    // Notifications (broadcast to users)
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/create', [NotificationController::class, 'create'])->name('notifications.create');
    Route::post('notifications', [NotificationController::class, 'store'])->name('notifications.store');
    Route::delete('notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
});
