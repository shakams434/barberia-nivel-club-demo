<?php

namespace App\Services;

use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class PhoneNumberNormalizer
{
    public function normalize(string $phone, string $countryCode = 'PE'): string
    {
        $util = PhoneNumberUtil::getInstance();

        try {
            $number = $util->parse(trim($phone), strtoupper($countryCode));
        } catch (\Throwable) {
            throw new \InvalidArgumentException('El número de WhatsApp no tiene un formato válido.');
        }

        if (! $util->isValidNumber($number)) {
            throw new \InvalidArgumentException('El número de WhatsApp no es válido para el país seleccionado.');
        }

        return $util->format($number, PhoneNumberFormat::E164);
    }
}
