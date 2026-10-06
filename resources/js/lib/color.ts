export function isHexColor(value: string): boolean {
    return /^#[0-9a-f]{6}$/i.test(value);
}

/** Luminância relativa (WCAG 2.x) de uma cor #rrggbb. */
function luminance(hex: string): number {
    const [r, g, b] = [1, 3, 5].map((i) => {
        const channel = parseInt(hex.slice(i, i + 2), 16) / 255;
        return channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
    });

    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

/**
 * Preto ou branco, o que tiver mais contraste sobre a cor de fundo.
 * Garante botões legíveis seja qual for a cor escolhida pelo negócio.
 */
export function readableTextColor(background: string): '#000000' | '#ffffff' {
    const l = luminance(background);
    const contrastWithWhite = 1.05 / (l + 0.05);
    const contrastWithBlack = (l + 0.05) / 0.05;

    return contrastWithWhite >= contrastWithBlack ? '#ffffff' : '#000000';
}
