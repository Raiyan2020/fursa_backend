-- ============================================================
-- Fursa Home CMS test data (fills null / empty home/ fields)
-- Run on the SAME database your Laravel API uses.
-- Safe to re-run (upserts).
-- ============================================================

-- 1) Hero + Share an idea
INSERT INTO `home_sections`
  (`slug`, `title_en`, `title_ar`, `description_en`, `description_ar`, `image`, `sort_order`, `is_deleted`, `created_at`, `updated_at`)
VALUES
  (
    'hero',
    'Volunteering is a lifestyle',
    'التطوع اسلوب حياة',
    NULL,
    NULL,
    NULL,
    1,
    0,
    NOW(),
    NOW()
  ),
  (
    'share_idea',
    'Share an idea',
    'شارك فكرة',
    'Sharing volunteer ideas is not just a contribution; it''s a lasting impact on others'' lives. A small idea can create a big change volunteering starts with a thought and thrives through giving.',
    'مشاركة أفكار التطوع ليست مجرد مساهمة؛ إنها أثر دائم في حياة الآخرين. فكرة صغيرة قد تصنع تغييراً كبيراً — التطوع يبدأ بفكرة وينمو بالعطاء.',
    'home_sections/share-idea.png',
    2,
    0,
    NOW(),
    NOW()
  )
ON DUPLICATE KEY UPDATE
  `title_en` = VALUES(`title_en`),
  `title_ar` = VALUES(`title_ar`),
  `description_en` = VALUES(`description_en`),
  `description_ar` = VALUES(`description_ar`),
  `image` = COALESCE(VALUES(`image`), `image`),
  `sort_order` = VALUES(`sort_order`),
  `is_deleted` = 0,
  `updated_at` = NOW();

-- 2) Why FORSA?
INSERT INTO `why_fursa_items`
  (`title_en`, `title_ar`, `icon`, `sort_order`, `is_deleted`, `created_at`, `updated_at`)
SELECT * FROM (
  SELECT 'Volunteer recognition' AS title_en, 'تقدير المتطوعين' AS title_ar, 'why_fursa/recognition.png' AS icon, 1 AS sort_order, 0 AS is_deleted, NOW() AS created_at, NOW() AS updated_at
  UNION ALL SELECT 'Opportunity matching', 'مطابقة الفرص', 'why_fursa/matching.png', 2, 0, NOW(), NOW()
  UNION ALL SELECT 'Community engagement', 'التفاعل المجتمعي', 'why_fursa/community.png', 3, 0, NOW(), NOW()
  UNION ALL SELECT 'Document your achievements', 'توثيق إنجازاتك', 'why_fursa/achievements.png', 4, 0, NOW(), NOW()
  UNION ALL SELECT 'Courses & skill building', 'دورات وبناء المهارات', 'why_fursa/courses.png', 5, 0, NOW(), NOW()
) AS seed
WHERE NOT EXISTS (
  SELECT 1 FROM `why_fursa_items` w WHERE w.`title_en` = seed.title_en AND w.`is_deleted` = 0
);

-- If rows already exist but you want to refresh titles/order:
UPDATE `why_fursa_items` SET `title_ar` = 'تقدير المتطوعين', `sort_order` = 1, `is_deleted` = 0, `updated_at` = NOW() WHERE `title_en` = 'Volunteer recognition';
UPDATE `why_fursa_items` SET `title_ar` = 'مطابقة الفرص', `sort_order` = 2, `is_deleted` = 0, `updated_at` = NOW() WHERE `title_en` = 'Opportunity matching';
UPDATE `why_fursa_items` SET `title_ar` = 'التفاعل المجتمعي', `sort_order` = 3, `is_deleted` = 0, `updated_at` = NOW() WHERE `title_en` = 'Community engagement';
UPDATE `why_fursa_items` SET `title_ar` = 'توثيق إنجازاتك', `sort_order` = 4, `is_deleted` = 0, `updated_at` = NOW() WHERE `title_en` = 'Document your achievements';
UPDATE `why_fursa_items` SET `title_ar` = 'دورات وبناء المهارات', `sort_order` = 5, `is_deleted` = 0, `updated_at` = NOW() WHERE `title_en` = 'Courses & skill building';

-- 3) Footer / social / copyright
INSERT INTO `site_settings`
  (`id`, `tiktok_url`, `twitter_url`, `youtube_url`, `instagram_url`, `copyright_en`, `copyright_ar`, `contact_email`, `created_at`, `updated_at`)
VALUES
  (
    1,
    'https://www.tiktok.com/@forsa',
    'https://x.com/forsa',
    'https://www.youtube.com/@forsa',
    'https://www.instagram.com/forsa',
    '© 2026 Forsa All rights reserved.',
    '© 2026 فرصة. جميع الحقوق محفوظة.',
    'forsa@joinforsa.net',
    NOW(),
    NOW()
  )
ON DUPLICATE KEY UPDATE
  `tiktok_url` = VALUES(`tiktok_url`),
  `twitter_url` = VALUES(`twitter_url`),
  `youtube_url` = VALUES(`youtube_url`),
  `instagram_url` = VALUES(`instagram_url`),
  `copyright_en` = VALUES(`copyright_en`),
  `copyright_ar` = VALUES(`copyright_ar`),
  `contact_email` = VALUES(`contact_email`),
  `updated_at` = NOW();

-- 4) Footer pages (about / privacy / terms)
INSERT INTO `pages`
  (`slug`, `title_en`, `title_ar`, `content_en`, `content_ar`, `is_deleted`, `created_at`, `updated_at`)
VALUES
  (
    'about',
    'About Us',
    'من نحن',
    '"Forsa" is an innovative volunteering platform.',
    '"فرصة" منصة مبتكرة للتطوع.',
    0,
    NOW(),
    NOW()
  ),
  (
    'privacy',
    'Privacy Policy',
    'سياسة الخصوصية',
    'We respect your privacy and protect your personal data.',
    'نحن نحترم خصوصيتك ونحمي بياناتك الشخصية.',
    0,
    NOW(),
    NOW()
  ),
  (
    'terms',
    'Terms of Use',
    'شروط الاستخدام',
    'By using Forsa you agree to the platform terms and community guidelines.',
    'باستخدامك لمنصة فرصة فإنك توافق على الشروط وإرشادات المجتمع.',
    0,
    NOW(),
    NOW()
  )
ON DUPLICATE KEY UPDATE
  `title_en` = VALUES(`title_en`),
  `title_ar` = VALUES(`title_ar`),
  `content_en` = VALUES(`content_en`),
  `content_ar` = VALUES(`content_ar`),
  `is_deleted` = 0,
  `updated_at` = NOW();

-- 5) Sponsor logo (fills sponsors[].logo null)
UPDATE `sponsors`
SET
  `sponsor_logo` = 'sponsors/sample-logo.png',
  `updated_at` = NOW()
WHERE `id` = 1
  AND (`sponsor_logo` IS NULL OR `sponsor_logo` = '');
