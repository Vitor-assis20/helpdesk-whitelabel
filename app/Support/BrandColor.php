<?php

namespace App\Support;

/**
 * Cor da marca do negócio e o texto legível sobre ela.
 *
 * Mesmo cálculo de resources/js/lib/color.ts, para e-mails (sem JavaScript).
 */
final class BrandColor
{
    public static function isValid(?string $hex): bool
    {
        return $hex !== null && preg_match('/^#[0-9a-f]{6}$/i', $hex) === 1;
    }

    /**
     * Preto ou branco, o que tiver mais contraste (WCAG 2.x) sobre a cor.
     */
    public static function readableText(string $hex): string
    {
        $luminance = self::luminance($hex);
        $contrastWithWhite = 1.05 / ($luminance + 0.05);
        $contrastWithBlack = ($luminance + 0.05) / 0.05;

        return $contrastWithWhite >= $contrastWithBlack ? '#ffffff' : '#000000';
    }

    private static function luminance(string $hex): float
    {
        [$r, $g, $b] = array_map(function (int $offset) use ($hex) {
            $channel = hexdec(substr($hex, $offset, 2)) / 255;

            return $channel <= 0.03928 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
        }, [1, 3, 5]);

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }
}
