<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

class YandexMarkupChangedException extends YandexParserException
{
    public function __construct(string $message = 'Формат или внутренняя разметка данных Яндекс.Карт изменилась')
    {
        parent::__construct($message);
    }
}
