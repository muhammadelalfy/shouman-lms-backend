<?php

declare(strict_types=1);

namespace Modules\Identity\Tests\Feature\Rules;

use Illuminate\Support\Facades\Validator;
use Modules\Identity\Rules\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PhoneNumberTest extends TestCase
{
    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function values(): array
    {
        return [
            'international egypt' => ['+201001234567', true],
            'local egypt' => ['01001234567', true],
            'international saudi' => ['+966500000000', true],
            'shortest allowed (8 digits)' => ['12345678', true],
            'longest allowed (15 digits)' => ['+123456789012345', true],
            'too short' => ['1234567', false],
            'too long' => ['+1234567890123456', false],
            'spaces' => ['+20 100 123 4567', false],
            'dashes' => ['010-0123-4567', false],
            'letters' => ['abcdefghij', false],
            'plus in the middle' => ['20+1001234567', false],
            'integer instead of string' => [201001234567, false],
        ];
    }

    #[DataProvider('values')]
    public function test_accepts_only_international_digit_format(mixed $value, bool $passes): void
    {
        $validator = Validator::make(['phone' => $value], ['phone' => [new PhoneNumber]]);

        $this->assertSame($passes, $validator->passes());
    }
}
