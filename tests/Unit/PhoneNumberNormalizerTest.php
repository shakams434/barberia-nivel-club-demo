<?php

namespace Tests\Unit;

use App\Services\PhoneNumberNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNumberNormalizerTest extends TestCase
{
    #[DataProvider('peruvianPhones')]
    public function test_it_normalizes_peruvian_phone_numbers(string $input, string $expected): void
    {
        $this->assertSame($expected, (new PhoneNumberNormalizer)->normalize($input, 'PE'));
    }

    public static function peruvianPhones(): array
    {
        return [
            ['987 654 321', '+51987654321'],
            ['+51 987-654-321', '+51987654321'],
            ['51987654321', '+51987654321'],
        ];
    }

    public function test_it_rejects_invalid_phone_numbers(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new PhoneNumberNormalizer)->normalize('123', 'PE');
    }
}
