<?php

namespace App\Enums;

/**
 * Where a banner is shown. `home` is the legacy carousel; the rest were added
 * so the opportunity/event pages get their own admin-managed banner.
 */
enum BannerPlacement: string
{
    case HOME = 'home';
    case OPPORTUNITIES = 'opportunities';
    case DEVELOPMENT = 'development';
    case EVENTS = 'events';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function labelEn(): string
    {
        return match ($this) {
            self::HOME => 'Home page',
            self::OPPORTUNITIES => 'Volunteer opportunities page',
            self::DEVELOPMENT => 'Development opportunities page',
            self::EVENTS => 'Events page',
        };
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::HOME => 'الصفحة الرئيسية',
            self::OPPORTUNITIES => 'صفحة فرص التطوع',
            self::DEVELOPMENT => 'صفحة فرص التطور',
            self::EVENTS => 'صفحة الفعاليات',
        };
    }
}
