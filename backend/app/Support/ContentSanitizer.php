<?php

declare(strict_types=1);

namespace App\Support;

class ContentSanitizer
{
    /**
     * Sanitize single-line plain text strings (names, addresses, levels).
     * Completely strips HTML tags and non-printable control characters.
     */
    public static function sanitizePlainText(?string $input, ?string $default = null): ?string
    {
        if ($input === null) {
            return $default;
        }

        // Strip all tags
        $cleaned = strip_tags($input);

        // Remove control characters (preserve UTF-8 letters, digits, spaces, punctuation)
        $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $cleaned);

        $trimmed = trim((string) $cleaned);

        return $trimmed !== '' ? $trimmed : $default;
    }

    /**
     * Sanitize multiline content such as review bodies and company responses.
     * Prevents Stored XSS (<script>, <iframe>, event handlers) while preserving
     * legitimate linebreaks, Russian text, quotes and emojis.
     */
    public static function sanitizeText(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        // 1. Remove dangerous active tags including their inner content
        $cleaned = preg_replace('/<(script|iframe|object|embed|style|link)[^>]*?>.*?<\/\\1>/si', '', $input);
        if ($cleaned === null) {
            $cleaned = $input;
        }

        // 2. Strip any remaining HTML tags
        $cleaned = strip_tags($cleaned);

        // 3. Normalize carriage returns to \n
        $cleaned = str_replace(["\r\n", "\r"], "\n", $cleaned);

        // 4. Remove control characters except \t and \n
        $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $cleaned);

        $trimmed = trim((string) $cleaned);

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * Sanitize URL, strictly ensuring only http or https scheme is allowed.
     * Protects against javascript:, data: or vbscript: injection in avatar URLs.
     */
    public static function sanitizeUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $trimmed = trim($url);
        if ($trimmed === '') {
            return null;
        }

        // Check for malicious pseudo-protocols
        if (preg_match('/^(?:javascript|data|vbscript):/i', $trimmed)) {
            return null;
        }

        // Ensure starts with http:// or https://
        if (! preg_match('/^https?:\/\//i', $trimmed)) {
            return null;
        }

        return filter_var($trimmed, FILTER_VALIDATE_URL) ? $trimmed : null;
    }
}
