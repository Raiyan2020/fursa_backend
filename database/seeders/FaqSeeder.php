<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $faqs = [
            [
                'question_en' => 'What is "Forsa"?',
                'question_ar' => 'ما هي منصة "فرصة"؟',
                'answer_en' => '"Forsa" is an innovative platform aimed at promoting a culture of volunteering and empowering individuals to find volunteer opportunities that match their interests and aspirations.',
                'answer_ar' => '"فرصة" هي منصة مبتكرة تهدف إلى تعزيز ثقافة التطوع وتمكين الأفراد من إيجاد فرص تطوعية تتناسب مع اهتماماتهم وتطلعاتهم.',
            ],
            [
                'question_en' => 'What can you do as a volunteer on "Forsa"?',
                'question_ar' => 'ما الذي يمكنك فعله كمتطوع فرد عبر منصة "فرصة"؟',
                'answer_en' => 'Join volunteer opportunities, launch initiatives, participate in workshops, share ideas in the community, and compete for achievements badges.',
                'answer_ar' => 'الانضمام إلى الفرص التطوعية، إطلاق المبادرات، المشاركة في الورش، مشاركة الأفكار في المجتمع، والتنافس على شارات الإنجاز.',
            ],
            [
                'question_en' => 'What Volunteer Teams can do via "Forsa"?',
                'question_ar' => 'ما الذي يمكن للفرق التطوعية فعله عبر منصة "فرصة"؟',
                'answer_en' => 'Launch initiatives, recruit volunteers, organize courses/events, compete in achievements, and document performance statistics.',
                'answer_ar' => 'إطلاق مبادرات، استقطاب متطوعين، تنظيم الدورات والفعاليات، التنافس في الإنجازات، وتوثيق الإحصائيات.',
            ],
            [
                'question_en' => 'How do I create an account?',
                'question_ar' => 'كيف أنشئ حسابًا؟',
                'answer_en' => 'Register with your email, verify the OTP sent to your inbox, then complete your volunteer or organization profile.',
                'answer_ar' => 'سجّل ببريدك الإلكتروني، فعّل الحساب عبر رمز OTP، ثم أكمل ملف المتطوع أو الجهة.',
            ],
            [
                'question_en' => 'How are volunteer hours calculated?',
                'question_ar' => 'كيف يتم احتساب ساعات التطوع؟',
                'answer_en' => 'Hours are calculated from attendance records on opportunities, and contribute to badges and rankings.',
                'answer_ar' => 'تُحسب الساعات من سجلات الحضور في الفرص، وتساهم في الشارات والترتيب.',
            ],
        ];

        foreach ($faqs as $faq) {
            Faq::query()->firstOrCreate(
                ['question_en' => $faq['question_en']],
                $faq
            );
        }
    }
}
