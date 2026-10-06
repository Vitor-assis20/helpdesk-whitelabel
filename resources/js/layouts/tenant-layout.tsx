import { brandStyle, TenantHeader } from '@/components/tenant-brand';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';

/**
 * Layout das páginas públicas, com a marca do negócio aplicada.
 */
export default function TenantLayout({ children }: PropsWithChildren) {
    const tenant = usePage<SharedData>().props.tenant!;

    return (
        <div className="bg-muted/40 min-h-svh" style={brandStyle(tenant.branding.primary_color)}>
            <TenantHeader tenant={tenant} />

            <main className="mx-auto max-w-3xl px-4 py-8">{children}</main>
        </div>
    );
}
