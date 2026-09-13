<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

class YandexCaptchaDetectedException extends YandexParserException
{
    public function __construct(string $message = 'Яндекс вернул требование прохождения капчи (SmartCaptcha/Bot protection)')
    {
        parent::__construct($message);
    }
}
