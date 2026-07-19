<?php

namespace Database\Seeders;

use App\Models\HomeSection;
use App\Models\SiteSetting;
use App\Models\WhyFursaItem;
use Illuminate\Database\Seeder;

class HomeCmsSeeder extends Seeder
{
    public function run(): void
    {
        HomeSection::query()->updateOrCreate(
            ['slug' => 'hero'],
            [
                'title_en' => 'Volunteering is a lifestyle',
                'title_ar' => 'التطوع اسلوب حياة',
                'description_en' => null,
                'description_ar' => null,
                'sort_order' => 1,
            ]
        );

        HomeSection::query()->updateOrCreate(
            ['slug' => 'share_idea'],
            [
                'title_en' => 'Share an idea',
                'title_ar' => 'شارك فكرة',
                'description_en' => 'Sharing volunteer ideas is not just a contribution; it\'s a lasting impact on others\' lives. A small idea can create a big change volunteering starts with a thought and thrives through giving.',
                'description_ar' => 'مشاركة أفكار التطوع ليست مجرد مساهمة؛ إنها أثر دائم في حياة الآخرين. فكرة صغيرة قد تصنع تغييراً كبيراً — التطوع يبدأ بفكرة وينمو بالعطاء.',
                'sort_order' => 2,
            ]
        );

        $whyItems = [
            ['title_en' => 'Volunteer recognition', 'title_ar' => 'تقدير المتطوعين', 'sort_order' => 1],
            ['title_en' => 'Opportunity matching', 'title_ar' => 'مطابقة الفرص', 'sort_order' => 2],
            ['title_en' => 'Community engagement', 'title_ar' => 'التفاعل المجتمعي', 'sort_order' => 3],
            ['title_en' => 'Document your achievements', 'title_ar' => 'توثيق إنجازاتك', 'sort_order' => 4],
            ['title_en' => 'Courses & skill building', 'title_ar' => 'دورات وبناء المهارات', 'sort_order' => 5],
        ];

        foreach ($whyItems as $item) {
            WhyFursaItem::query()->updateOrCreate(
                ['title_en' => $item['title_en']],
                $item
            );
        }

        SiteSetting::query()->updateOrCreate(
            ['id' => 1],
            [
                'tiktok_url' => null,
                'twitter_url' => null,
                'youtube_url' => null,
                'instagram_url' => null,
                'copyright_en' => '© '.now()->year.' Forsa All rights reserved.',
                'copyright_ar' => '© '.now()->year.' فرصة. جميع الحقوق محفوظة.',
                'contact_email' => 'forsa@joinforsa.net',
            ]
        );
    }
}
