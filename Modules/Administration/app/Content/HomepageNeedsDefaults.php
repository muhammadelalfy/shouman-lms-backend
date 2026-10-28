<?php

declare(strict_types=1);

namespace Modules\Administration\Content;

final class HomepageNeedsDefaults
{
    public const array IMAGE_KEYS = ['religion', 'student', 'family', 'renewal', 'quran', 'prayer', 'repentance', 'closeness', 'mosque'];

    /** @return array<string, mixed> */
    public static function content(): array
    {
        $subjects = [
            ['religion', 'أريد أن أتعلم ديني', 'العقيدة - الفقه - السيرة - التفسير', 'العقيدة'],
            ['student', 'أنا طالب / شاب', 'الجامعة - الصحبة - النجاح - الفتن', 'الشباب'],
            ['family', 'عندي مشكلة أسرية', 'التربية - الأبناء - العلاقات', 'الأسرة'],
            ['renewal', 'أعاني من الفتور', 'معرفة الله - إجابة الإيمان - أعمال القلوب', 'الإيمان'],
            ['quran', 'أريد أفهم القرآن', 'تدبر - تفسير - حفظ - تعاهد', 'القرآن'],
            ['prayer', 'أريد أن أنتظم في الصلاة', 'الصلاة - الخشوع - قيام الليل', 'الصلاة'],
            ['repentance', 'أريد أن أتوب', 'التوبة - الذنوب - بداية الطريق', 'التوبة'],
            ['closeness', 'أريد أن أقترب من الله', 'محبة الله - حسن الظن بالله', 'محبة الله'],
            ['mosque', 'اكتشف المزيد', '', ''],
        ];

        return [
            'enabled' => true,
            'title' => 'ماذا تحتاج الآن ؟',
            'subtitle' => 'اختر ما يناسب حالتك لنقترح لك محتوى يساعدك في رحلتك الإيمانية',
            'cta_label' => 'عرض جميع التصنيفات',
            'cta_href' => '/catalog',
            'cards' => array_map(fn (array $subject): array => [
                'id' => $subject[0], 'image_key' => $subject[0], 'title' => $subject[1],
                'description' => $subject[2], 'href' => '/catalog'.($subject[3] !== '' ? '?q='.rawurlencode($subject[3]) : ''),
                'style' => $subject[0] === 'mosque' ? 'photo' : 'icon', 'enabled' => true,
            ], $subjects),
        ];
    }
}
