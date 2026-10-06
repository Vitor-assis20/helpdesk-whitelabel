import { Head, useForm } from '@inertiajs/react';
import { Check, Copy, ExternalLink, LoaderCircle } from 'lucide-react';
import { type FormEventHandler, type ReactNode, useState } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { isHexColor, readableTextColor } from '@/lib/color';
import { type BreadcrumbItem } from '@/types';

type Hour = { weekday: number; starts_at: string; ends_at: string };
type Policy = { label: string; first_response_minutes: number; resolution_minutes: number };

interface SettingsProps {
    business: { name: string; slug: string; primary_color: string | null; portal_url: string; widget_snippet: string };
    hours: Hour[];
    policies: Record<string, Policy>;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Configurações', href: '/painel/configuracoes' }];
const WEEKDAYS = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
// Semana começando na segunda, como se lê uma agenda no Brasil.
const WEEK_ORDER = [1, 2, 3, 4, 5, 6, 0];
const PRESETS = ['#7c3aed', '#1d4ed8', '#0f766e', '#b45309', '#be123c', '#111827'];

function Section({ title, description, children }: { title: string; description: string; children: ReactNode }) {
    return (
        <section className="space-y-4 rounded-xl border p-5">
            <div>
                <h2 className="font-semibold">{title}</h2>
                <p className="text-muted-foreground text-sm">{description}</p>
            </div>
            {children}
        </section>
    );
}

function SaveButton({ processing, saved }: { processing: boolean; saved: boolean }) {
    return (
        <div className="flex items-center gap-3">
            <Button type="submit" disabled={processing}>
                {processing && <LoaderCircle className="size-4 animate-spin" />}
                Salvar
            </Button>
            {saved && <span className="text-muted-foreground text-sm">Salvo.</span>}
        </div>
    );
}

function BrandingForm({ business }: { business: SettingsProps['business'] }) {
    const form = useForm({ name: business.name, primary_color: business.primary_color ?? PRESETS[0] });
    const [copied, setCopied] = useState(false);
    const color = isHexColor(form.data.primary_color) ? form.data.primary_color : PRESETS[0];

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.put(route('panel.settings.branding'), { preserveScroll: true });
    };

    const copy = async () => {
        await navigator.clipboard.writeText(business.widget_snippet);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <Section title="Marca e canais" description="Como sua empresa aparece no portal, no widget e nos e-mails.">
            <form onSubmit={submit} className="space-y-4">
                <div className="grid gap-2">
                    <Label htmlFor="name">Nome da empresa</Label>
                    <Input id="name" required value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                    <InputError message={form.errors.name} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="color">Cor principal</Label>
                    <div className="flex flex-wrap items-center gap-2">
                        {PRESETS.map((preset) => (
                            <button
                                key={preset}
                                type="button"
                                aria-label={`Usar ${preset}`}
                                onClick={() => form.setData('primary_color', preset)}
                                className="flex size-8 items-center justify-center rounded-full"
                                style={{ backgroundColor: preset, color: readableTextColor(preset) }}
                            >
                                {form.data.primary_color.toLowerCase() === preset && <Check className="size-4" />}
                            </button>
                        ))}
                        <Input
                            id="color"
                            className="w-28 font-mono"
                            maxLength={7}
                            value={form.data.primary_color}
                            onChange={(e) => form.setData('primary_color', e.target.value)}
                        />
                        <span
                            className="rounded-md px-3 py-1.5 text-sm font-medium"
                            style={{ backgroundColor: color, color: readableTextColor(color) }}
                        >
                            Prévia do botão
                        </span>
                    </div>
                    <InputError message={form.errors.primary_color} />
                </div>
                <SaveButton processing={form.processing} saved={form.recentlySuccessful} />
            </form>

            <div className="space-y-2 border-t pt-4">
                <p className="text-sm font-medium">Portal do cliente</p>
                <a href={business.portal_url} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-1 text-sm underline">
                    {business.portal_url} <ExternalLink className="size-3" />
                </a>
                <p className="pt-2 text-sm font-medium">Widget para o seu site</p>
                <p className="text-muted-foreground text-sm">Cole esta linha antes do &lt;/body&gt; das páginas do seu site:</p>
                <div className="flex items-center gap-2">
                    <code className="bg-muted min-w-0 flex-1 truncate rounded px-3 py-2 text-xs">{business.widget_snippet}</code>
                    <Button type="button" variant="outline" size="sm" onClick={copy}>
                        {copied ? <Check /> : <Copy />} {copied ? 'Copiado' : 'Copiar'}
                    </Button>
                </div>
            </div>
        </Section>
    );
}

function HoursForm({ hours }: { hours: Hour[] }) {
    // Um intervalo por dia na tela; o servidor aceita vários.
    const initial = Object.fromEntries(WEEK_ORDER.map((weekday) => [weekday, hours.find((hour) => hour.weekday === weekday) ?? null]));
    const [days, setDays] = useState<Record<number, Hour | null>>(initial);
    const form = useForm<{ hours: Hour[] }>({ hours });
    const errors = form.errors as Record<string, string>;
    const enabled = WEEK_ORDER.filter((weekday) => days[weekday]);

    const update = (weekday: number, value: Hour | null) => {
        const next = { ...days, [weekday]: value };
        setDays(next);
        form.setData(
            'hours',
            WEEK_ORDER.map((day) => next[day]).filter((hour): hour is Hour => hour !== null),
        );
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.put(route('panel.settings.hours'), { preserveScroll: true });
    };

    return (
        <Section
            title="Horário de atendimento"
            description="Os prazos de SLA só contam dentro do expediente, no fuso da empresa. Vale para chamados novos."
        >
            <form onSubmit={submit} className="space-y-3">
                <div className="divide-y rounded-lg border">
                    {WEEK_ORDER.map((weekday) => {
                        const hour = days[weekday];
                        const index = enabled.indexOf(weekday);
                        return (
                            <div key={weekday} className="flex flex-wrap items-center gap-3 p-3">
                                <label className="flex w-28 items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={hour !== null}
                                        onChange={(e) => update(weekday, e.target.checked ? { weekday, starts_at: '09:00', ends_at: '18:00' } : null)}
                                    />
                                    {WEEKDAYS[weekday]}
                                </label>
                                {hour ? (
                                    <div className="flex items-center gap-2">
                                        <Input
                                            type="time"
                                            aria-label={`Início ${WEEKDAYS[weekday]}`}
                                            className="w-32"
                                            value={hour.starts_at}
                                            onChange={(e) => update(weekday, { ...hour, starts_at: e.target.value })}
                                        />
                                        <span className="text-muted-foreground text-sm">às</span>
                                        <Input
                                            type="time"
                                            aria-label={`Fim ${WEEKDAYS[weekday]}`}
                                            className="w-32"
                                            value={hour.ends_at}
                                            onChange={(e) => update(weekday, { ...hour, ends_at: e.target.value })}
                                        />
                                    </div>
                                ) : (
                                    <span className="text-muted-foreground text-sm">Fechado</span>
                                )}
                                <InputError message={errors[`hours.${index}.starts_at`] ?? errors[`hours.${index}.ends_at`]} />
                            </div>
                        );
                    })}
                </div>
                <InputError message={errors.hours} />
                <SaveButton processing={form.processing} saved={form.recentlySuccessful} />
            </form>
        </Section>
    );
}

function SlaForm({ policies }: { policies: Record<string, Policy> }) {
    const toHours = (minutes: number) => String(Math.round((minutes / 60) * 100) / 100).replace('.', ',');
    const [values, setValues] = useState(() =>
        Object.fromEntries(
            Object.entries(policies).map(([priority, policy]) => [
                priority,
                { first: toHours(policy.first_response_minutes), resolution: toHours(policy.resolution_minutes) },
            ]),
        ),
    );
    const form = useForm({ policies: {} as Record<string, { first_response_minutes: number; resolution_minutes: number }> });
    const errors = form.errors as Record<string, string>;
    const toMinutes = (hours: string) => Math.round(Number(hours.replace(',', '.')) * 60);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform(() => ({
            policies: Object.fromEntries(
                Object.entries(values).map(([priority, value]) => [
                    priority,
                    { first_response_minutes: toMinutes(value.first), resolution_minutes: toMinutes(value.resolution) },
                ]),
            ),
        }));
        form.put(route('panel.settings.sla'), { preserveScroll: true });
    };

    return (
        <Section title="Prazos de SLA" description="Em horas úteis, por prioridade (0,5 = 30 minutos). Vale para chamados novos.">
            <form onSubmit={submit} className="space-y-3">
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3 font-medium">Prioridade</th>
                                <th className="p-3 font-medium">Primeira resposta (h)</th>
                                <th className="p-3 font-medium">Resolução (h)</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {Object.entries(policies).map(([priority, policy]) => (
                                <tr key={priority}>
                                    <td className="p-3">{policy.label}</td>
                                    {(['first', 'resolution'] as const).map((field) => (
                                        <td key={field} className="p-3">
                                            <Input
                                                inputMode="decimal"
                                                aria-label={`${field === 'first' ? 'Primeira resposta' : 'Resolução'} · ${policy.label}`}
                                                className="w-28"
                                                value={values[priority][field]}
                                                onChange={(e) =>
                                                    setValues({ ...values, [priority]: { ...values[priority], [field]: e.target.value } })
                                                }
                                            />
                                            <InputError
                                                message={
                                                    errors[
                                                        `policies.${priority}.${field === 'first' ? 'first_response_minutes' : 'resolution_minutes'}`
                                                    ]
                                                }
                                            />
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <SaveButton processing={form.processing} saved={form.recentlySuccessful} />
            </form>
        </Section>
    );
}

export default function Settings({ business, hours, policies }: SettingsProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Configurações" />
            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4">
                <BrandingForm business={business} />
                <HoursForm hours={hours} />
                <SlaForm policies={policies} />
            </div>
        </AppLayout>
    );
}
