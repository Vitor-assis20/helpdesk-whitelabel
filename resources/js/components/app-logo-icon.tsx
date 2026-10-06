import { SVGAttributes } from 'react';

/**
 * Ícone da plataforma (balão de conversa).
 *
 * Desenho em traço: o fill inline anula o "fill-current" que os layouts do
 * starter kit aplicam ao ícone, que preencheria as formas.
 */
export default function AppLogoIcon({ style, ...props }: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            style={{ ...style, fill: 'none' }}
            viewBox="0 0 24 24"
            xmlns="http://www.w3.org/2000/svg"
            stroke="currentColor"
            strokeWidth={2}
            strokeLinecap="round"
            strokeLinejoin="round"
        >
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
            <path d="M8 9h8M8 13h5" />
        </svg>
    );
}
