import { Head, router, useForm, usePage } from '@inertiajs/react';
import { LoaderCircle, Mail, Trash2, UserPlus } from 'lucide-react';
import { type FormEventHandler, type ReactNode } from 'react';

import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime, timeAgo } from '@/lib/time';
import { type BreadcrumbItem } from '@/types';
import { type TenantRole } from '@/types/panel';

type Role = { value: Exclude<TenantRole, 'owner'>; label: string };

interface Member {
    id: number;
    name: string;
    email: string;
    role: TenantRole;
    role_label: string;
    joined_at: string;
    manageable: boolean;
}

interface Invitation {
    id: number;
    email: string;
    role: TenantRole;
    role_label: string;
    invited_by: string | null;
    expires_at: string;
    expired: boolean;
    manageable: boolean;
}

interface TeamProps {
    members: Member[];
    invitations: Invitation[];
    roles: Role[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Equipe', href: '/painel/equipe' }];

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

function RoleSelect({ roles, value, onChange, label }: { roles: Role[]; value: string; onChange: (value: string) => void; label: string }) {
    return (
        <Select value={value} onValueChange={onChange}>
            <SelectTrigger aria-label={label} className="w-44">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                {roles.map((role) => (
                    <SelectItem key={role.value} value={role.value}>
                        {role.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

function InviteForm({ roles }: { roles: Role[] }) {
    const form = useForm({ email: '', role: roles[roles.length - 1]?.value ?? 'agent' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('panel.team.invite'), { preserveScroll: true, onSuccess: () => form.reset('email') });
    };

    return (
        <Section
            title="Convidar para a equipe"
            description="A pessoa recebe um link por e-mail, válido por 7 dias, e entra com a conta desse e-mail."
        >
            <form onSubmit={submit} className="flex flex-wrap items-start gap-3">
                <div className="grid min-w-60 flex-1 gap-2">
                    <Label htmlFor="email" className="sr-only">
                        E-mail
                    </Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        placeholder="nome@empresa.com"
                        value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)}
                    />
                    <InputError message={form.errors.email} />
                </div>
                <RoleSelect
                    roles={roles}
                    label="Papel do convidado"
                    value={form.data.role}
                    onChange={(role) => form.setData('role', role as Role['value'])}
                />
                <Button type="submit" disabled={form.processing}>
                    {form.processing ? <LoaderCircle className="size-4 animate-spin" /> : <UserPlus className="size-4" />}
                    Convidar
                </Button>
            </form>
            {form.recentlySuccessful && <p className="text-muted-foreground text-sm">Convite enviado.</p>}
        </Section>
    );
}

export default function Team({ members, invitations, roles }: TeamProps) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;

    const changeRole = (member: Member, role: string) => router.patch(route('panel.team.role', member.id), { role }, { preserveScroll: true });

    const remove = (member: Member) => {
        if (confirm(`Remover ${member.name} da equipe? Os chamados com essa pessoa como responsável ficam sem responsável.`)) {
            router.delete(route('panel.team.remove', member.id), { preserveScroll: true });
        }
    };

    const revoke = (invitation: Invitation) => {
        if (confirm(`Cancelar o convite para ${invitation.email}? O link deixa de funcionar.`)) {
            router.delete(route('panel.team.revoke', invitation.id), { preserveScroll: true });
        }
    };

    const resend = (invitation: Invitation) =>
        router.post(route('panel.team.invite'), { email: invitation.email, role: invitation.role }, { preserveScroll: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Equipe" />
            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4">
                {roles.length > 0 && <InviteForm roles={roles} />}

                <Section
                    title="Membros"
                    description="Administradores acessam as configurações e convidam atendentes; só o dono gerencia administradores."
                >
                    <InputError message={errors.member} />
                    <ul className="divide-y rounded-lg border">
                        {members.map((member) => (
                            <li key={member.id} className="flex flex-wrap items-center gap-3 p-3">
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-medium">{member.name}</p>
                                    <p className="text-muted-foreground truncate text-sm">{member.email}</p>
                                </div>
                                {member.manageable ? (
                                    <>
                                        <RoleSelect
                                            roles={roles}
                                            label={`Papel de ${member.name}`}
                                            value={member.role}
                                            onChange={(role) => changeRole(member, role)}
                                        />
                                        <Button variant="ghost" size="icon" aria-label={`Remover ${member.name}`} onClick={() => remove(member)}>
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </>
                                ) : (
                                    <Badge variant="secondary">{member.role_label}</Badge>
                                )}
                            </li>
                        ))}
                    </ul>
                </Section>

                {invitations.length > 0 && (
                    <Section title="Convites pendentes" description="Reenviar gera um link novo e o anterior deixa de valer.">
                        <InputError message={errors.invitation} />
                        <ul className="divide-y rounded-lg border">
                            {invitations.map((invitation) => (
                                <li key={invitation.id} className="flex flex-wrap items-center gap-3 p-3">
                                    <Mail className="text-muted-foreground size-4" />
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium">{invitation.email}</p>
                                        <p className="text-muted-foreground text-sm" title={formatDateTime(invitation.expires_at)}>
                                            {invitation.role_label}
                                            {invitation.invited_by && ` · convidado por ${invitation.invited_by}`} ·{' '}
                                            {invitation.expired ? 'expirado' : `expira ${timeAgo(invitation.expires_at)}`}
                                        </p>
                                    </div>
                                    {invitation.manageable && (
                                        <>
                                            <Button variant="outline" size="sm" onClick={() => resend(invitation)}>
                                                Reenviar
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Cancelar convite para ${invitation.email}`}
                                                onClick={() => revoke(invitation)}
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </Section>
                )}
            </div>
        </AppLayout>
    );
}
