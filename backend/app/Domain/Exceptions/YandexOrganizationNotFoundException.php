<?php

namespace App\Domain\Exceptions;

class YandexOrganizationNotFoundException extends YandexParserException
{
    public function __construct(string $message = 'Организация не найдена на Яндекс.Картах')
    {
        parent::__construct($message);
    }
}
