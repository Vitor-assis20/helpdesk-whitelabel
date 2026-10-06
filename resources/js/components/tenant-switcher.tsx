import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';

/**
 * Troca o negócio ativo do painel. Só aparece para quem tem mais de um.
 */
export function TenantSwitcher() {
    const { panel } = usePage<SharedData>().props;

    if (!panel || panel.tenants.length < 2) return null;

    return (
        <div className="px-2 group-data-[collapsible=icon]:hidden">
            <Select
                value={String(panel.current.id)}
                onValueChange={(value) => router.post(route('panel.switch-tenant'), { tenant_id: Number(value) })}
            >
                <SelectTrigger aria-label="Negócio">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {panel.tenants.map((tenant) => (
                        <SelectItem key={tenant.id} value={String(tenant.id)}>
                            {tenant.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}
