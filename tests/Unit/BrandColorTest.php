<?php

namespace Tests\Unit;

use App\Support\BrandColor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BrandColorTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function colors(): array
    {
        return [
            'laranja escuro' => ['#b45309', '#ffffff'],
            'verde-azulado' => ['#0f766e', '#ffffff'],
            'quase preto' => ['#111827', '#ffffff'],
            'amarelo claro' => ['#fde047', '#000000'],
            'branco' => ['#ffffff', '#000000'],
            'maiúsculas' => ['#FDE047', '#000000'],
        ];
    }

    #[DataProvider('colors')]
    public function test_picks_the_text_color_with_more_contrast(string $background, string $expected)
    {
        $this->assertSame($expected, BrandColor::readableText($background));
    }

    public function test_validates_hex_colors()
    {
        $this->assertTrue(BrandColor::isValid('#b45309'));
        $this->assertFalse(BrandColor::isValid(null));
        $this->assertFalse(BrandColor::isValid('red'));
        $this->assertFalse(BrandColor::isValid('#b45309; background: url(x)'));
    }
}
