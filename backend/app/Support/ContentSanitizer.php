<?php

declare(strict_types=1);

namespace App\Support;

class ContentSanitizer
{
    /**
     * Очистка однострочных текстовых полей (имена авторов, статусы, адреса).
     * Полностью вырезает HTML-теги и непечатаемые управляющие символы.
     */
    public static function sanitizePlainText(?string $input, ?string $default = null): ?string
    {
        if ($input === null) {
            return $default;
        }

        // Удаление HTML-тегов
        $cleaned = strip_tags($input);

        // Очистка непечатаемых управляющих символов с сохранением UTF-8
        $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $cleaned);

        $trimmed = trim((string) $cleaned);

        return $trimmed !== '' ? $trimmed : $default;
    }

    /**
     * Очистка многострочного контента (тексты отзывов, ответы бизнеса).
     * Защищает от Stored XSS (<script>, <iframe>, onload/onerror) с сохранением
     * легитимных переносов строк, русской типографики, кавычек и эмодзи.
     */
    public static function sanitizeText(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        // 1. Удаление опасных активных тегов вместе с их содержимым
        $cleaned = preg_replace('/<(script|iframe|object|embed|style|link)[^>]*?>.*?<\/\\1>/si', '', $input);
        if ($cleaned === null) {
            $cleaned = $input;
        }

        // 2. Очистка остальных HTML-тегов
        $cleaned = strip_tags($cleaned);

        // 3. Нормализация переводов строк к \n
        $cleaned = str_replace(["\r\n", "\r"], "\n", $cleaned);

        // 4. Удаление битых управляющих символов (кроме табуляций и переносов строк)
        $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $cleaned);

        $trimmed = trim((string) $cleaned);

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * Валидация и санитизация URL, разрешая исключительно схемы http:// и https://.
     * Защищает от XSS-векторов через javascript:, data: или vbscript: в URL аватаров.
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

        // Блокировка опасных псевдопротоколов
        if (preg_match('/^(?:javascript|data|vbscript):/i', $trimmed)) {
            return null;
        }

        // Проверка безопасного сетевого протокола http:// или https://
        if (! preg_match('/^https?:\/\//i', $trimmed)) {
            return null;
        }

        return filter_var($trimmed, FILTER_VALIDATE_URL) ? $trimmed : null;
    }

    /**
     * Санитизация массива фотографий отзыва.
     * Проверяет безопасность URL превью и полного изображения.
     *
     * @param  array<mixed>|null  $photos
     * @return array<int, array{id: string, preview_url: string, full_url: string}>|null
     */
    public static function sanitizePhotos(?array $photos): ?array
    {
        if (empty($photos)) {
            return null;
        }

        $sanitized = [];

        foreach ($photos as $photo) {
            if (! is_array($photo)) {
                continue;
            }

            $id = isset($photo['id']) ? self::sanitizePlainText((string) $photo['id']) : null;
            $previewUrl = isset($photo['preview_url']) ? self::sanitizeUrl((string) $photo['preview_url']) : null;
            $fullUrl = isset($photo['full_url']) ? self::sanitizeUrl((string) $photo['full_url']) : null;

            if ($previewUrl === null && $fullUrl === null) {
                continue;
            }

            $sanitized[] = [
                'id' => $id ?? md5(($previewUrl ?? '') . ($fullUrl ?? '')),
                'preview_url' => $previewUrl ?? $fullUrl ?? '',
                'full_url' => $fullUrl ?? $previewUrl ?? '',
            ];
        }

        return ! empty($sanitized) ? $sanitized : null;
    }
}
