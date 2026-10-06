import { isHexColor, readableTextColor } from '@/lib/color';
import { type Tenant } from '@/types/portal';
import { type CSSProperties } from 'react';

/**
 * Sobrescreve as variáveis de tema com a cor do negócio: todo componente de
 * UI dentro do elemento herda a marca, com texto legível sobre ela.
 */
export function brandStyle(color: string | null | undefined): CSSProperties {
    if (!color || !isHexColor(color)) return {};

    const foreground = readableTextColor(color);

    // O tema usa @theme (não inline): --color-primary é resolvido no :root a
    // partir de --primary, e as classes (bg-primary etc.) leem --color-*.
    // Por isso as duas variáveis precisam ser sobrescritas aqui.
    return {
        '--primary': color,
        '--primary-foreground': foreground,
        '--ring': color,
        '--color-primary': color,
        '--color-primary-foreground': foreground,
        '--color-ring': color,
    } as CSSProperties;
}

function initials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word[0].toUpperCase())
        .join('');
}

export function TenantLogo({ name, logoUrl, className = 'size-10' }: { name: string; logoUrl?: string | null; className?: string }) {
    return logoUrl ? (
        <img src={logoUrl} alt="" className={`${className} rounded-full object-cover`} />
    ) : (
        <div className={`${className} bg-primary text-primary-foreground flex items-center justify-center rounded-full text-sm font-semibold`}>
            {initials(name)}
        </div>
    );
}

export function TenantHeader({ tenant }: { tenant: Pick<Tenant, 'name' | 'branding'> }) {
    return (
        <header className="bg-background border-b">
            <div className="mx-auto flex max-w-3xl items-center gap-3 px-4 py-4">
                <TenantLogo name={tenant.name} logoUrl={tenant.branding.logo_url} />
                <span className="text-lg font-semibold">{tenant.name}</span>
            </div>
        </header>
    );
}
