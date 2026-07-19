<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            [
                'slug' => 'about',
                'title_en' => 'About Us',
                'title_ar' => 'من نحن',
                'content_en' => '"Forsa" is an innovative platform aimed at promoting a culture of volunteering and empowering individuals and organizations to find and create volunteer opportunities that match their interests and aspirations.',
                'content_ar' => '"فرصة" منصة مبتكرة تهدف إلى تعزيز ثقافة التطوع وتمكين الأفراد والجهات من إيجاد وإطلاق فرص تطوعية تتناسب مع اهتماماتهم وتطلعاتهم.',
            ],
            [
                'slug' => 'privacy',
                'title_en' => 'Privacy Policy',
                'title_ar' => 'سياسة الخصوصية',
                'content_en' => 'We respect your privacy. Personal data collected through the Forsa platform is used to provide and improve our services, manage accounts, and communicate with users. We do not sell your personal information to third parties.',
                'content_ar' => 'نحن نحترم خصوصيتك. تُستخدم البيانات الشخصية التي يتم جمعها عبر منصة فرصة لتقديم خدماتنا وتحسينها، وإدارة الحسابات، والتواصل مع المستخدمين. لا نبيع معلوماتك الشخصية لأطراف ثالثة.',
            ],
            [
                'slug' => 'terms',
                'title_en' => 'Terms of Use',
                'title_ar' => 'شروط الاستخدام',
                'content_en' => 'By using the Forsa platform, you agree to comply with applicable laws and community guidelines. Accounts may be suspended for misuse, harmful content, or violation of platform rules. Content and branding remain the property of their respective owners.',
                'content_ar' => 'باستخدامك لمنصة فرصة فإنك توافق على الالتزام بالقوانين المعمول بها وإرشادات المجتمع. قد يتم إيقاف الحسابات في حال إساءة الاستخدام أو المحتوى الضار أو مخالفة قواعد المنصة. المحتوى والعلامات التجارية تظل ملكًا لأصحابها.',
            ],
        ];

        foreach ($pages as $page) {
            Page::query()->updateOrCreate(
                ['slug' => $page['slug']],
                $page
            );
        }
    }
}
