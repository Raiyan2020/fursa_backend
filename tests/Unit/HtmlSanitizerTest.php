<?php

namespace Tests\Unit;

use App\Support\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

/**
 * BE-37: user-authored rich text was stored unsanitized, so markup submitted
 * to an opportunity description was served verbatim to every reader.
 */
class HtmlSanitizerTest extends TestCase
{
    /**
     * @return list<array{0: string}>
     */
    public static function scriptPayloads(): array
    {
        return [
            ['<script>alert(1)</script>'],
            ['<img src=x onerror="alert(1)">'],
            ['<p onclick="alert(1)">text</p>'],
            ['<iframe src="https://evil.test"></iframe>'],
            ['<a href="javascript:alert(1)">click</a>'],
            ['<a href="JaVaScRiPt:alert(1)">click</a>'],
            ["<a href=\"java\tscript:alert(1)\">click</a>"],
            ['<svg onload="alert(1)"></svg>'],
            ['<body onload="alert(1)">x</body>'],
            ['<style>body{background:url("javascript:alert(1)")}</style>'],
            ['<img src="data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=">'],
            ['<object data="evil.swf"></object>'],
            ['<form action="https://evil.test"><input name="a"></form>'],
        ];
    }

    /**
     * @dataProvider scriptPayloads
     */
    public function test_it_strips_script_vectors(string $payload): void
    {
        $clean = (string) HtmlSanitizer::clean($payload);

        $this->assertStringNotContainsStringIgnoringCase('<script', $clean);
        $this->assertStringNotContainsStringIgnoringCase('<iframe', $clean);
        $this->assertStringNotContainsStringIgnoringCase('<object', $clean);
        $this->assertStringNotContainsStringIgnoringCase('<style', $clean);
        $this->assertStringNotContainsStringIgnoringCase('onerror', $clean);
        $this->assertStringNotContainsStringIgnoringCase('onclick', $clean);
        $this->assertStringNotContainsStringIgnoringCase('onload', $clean);
        $this->assertStringNotContainsStringIgnoringCase('javascript:', $clean);
        $this->assertStringNotContainsStringIgnoringCase('data:', $clean);
    }

    public function test_it_keeps_the_formatting_the_editor_emits(): void
    {
        $html = '<p><strong>Bold</strong> and <em>italic</em></p>'
            .'<ul><li>one</li><li>two</li></ul>'
            .'<h2>Heading</h2><blockquote>quote</blockquote>';

        $clean = (string) HtmlSanitizer::clean($html);

        foreach (['<p>', '<strong>', '<em>', '<ul>', '<li>', '<h2>', '<blockquote>'] as $tag) {
            $this->assertStringContainsString($tag, $clean, "Expected {$tag} to survive.");
        }
    }

    public function test_it_keeps_safe_links_and_images(): void
    {
        $clean = (string) HtmlSanitizer::clean(
            '<a href="https://fursa.test/x" title="t">link</a><img src="/img/a.png" alt="a">'
        );

        $this->assertStringContainsString('href="https://fursa.test/x"', $clean);
        $this->assertStringContainsString('src="/img/a.png"', $clean);
        $this->assertStringContainsString('alt="a"', $clean);
    }

    public function test_it_preserves_arabic_text(): void
    {
        $clean = (string) HtmlSanitizer::clean('<p>تنظيف الشاطئ مع <strong>فرصة</strong></p>');

        $this->assertStringContainsString('تنظيف الشاطئ', $clean);
        $this->assertStringContainsString('فرصة', $clean);
    }

    public function test_it_keeps_text_when_unwrapping_a_disallowed_tag(): void
    {
        // The tag goes, the words stay.
        $clean = (string) HtmlSanitizer::clean('<marquee>important notice</marquee>');

        $this->assertStringNotContainsStringIgnoringCase('<marquee', $clean);
        $this->assertStringContainsString('important notice', $clean);
    }

    public function test_it_drops_script_content_rather_than_exposing_it_as_text(): void
    {
        // Unwrapping <script> would turn the code into visible text; it must go.
        $clean = (string) HtmlSanitizer::clean('<p>before</p><script>alert("pwned")</script><p>after</p>');

        $this->assertStringContainsString('before', $clean);
        $this->assertStringContainsString('after', $clean);
        $this->assertStringNotContainsString('pwned', $clean);
    }

    public function test_it_adds_noopener_to_new_tab_links(): void
    {
        $clean = (string) HtmlSanitizer::clean('<a href="https://x.test" target="_blank">x</a>');

        $this->assertStringContainsString('noopener', $clean);
    }

    public function test_it_leaves_null_and_blank_alone(): void
    {
        $this->assertNull(HtmlSanitizer::clean(null));
        $this->assertSame('', HtmlSanitizer::clean(''));
        $this->assertSame('   ', HtmlSanitizer::clean('   '));
    }

    public function test_it_leaves_plain_text_untouched(): void
    {
        $this->assertSame('Beach cleanup 2026', HtmlSanitizer::clean('Beach cleanup 2026'));
    }

    public function test_clean_fields_only_touches_the_named_keys(): void
    {
        $data = [
            'title_en' => '<script>alert(1)</script>Title',
            'description_en' => '<p>ok</p><script>alert(1)</script>',
            'participants_needed' => 10,
        ];

        $result = HtmlSanitizer::cleanFields($data, ['description_en']);

        // Untouched key keeps its raw value; only description_en is cleaned.
        $this->assertSame('<script>alert(1)</script>Title', $result['title_en']);
        $this->assertStringNotContainsString('<script', $result['description_en']);
        $this->assertSame(10, $result['participants_needed']);
    }

    public function test_clean_fields_tolerates_missing_keys(): void
    {
        $result = HtmlSanitizer::cleanFields(['a' => 1], ['description_en', 'description_ar']);

        $this->assertSame(['a' => 1], $result);
    }
}
