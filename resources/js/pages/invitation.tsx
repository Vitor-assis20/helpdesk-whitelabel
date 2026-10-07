import { Head, Link, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { type FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';

interface InvitationProps {
    invitation: { tenant: string; email: string; role_label: string; invited_by: string | null; token: string } | null;
    state: 'invalid' | 'guest' | 'wrong_account' | 'ready';
}

export default function Invitation({ invitation, state }: InvitationProps) {
    const form = useForm({});

    if (invitation === null) {
        return (
            <AuthLayout
                title="Convite indisponível"
                description="Este convite expirou, foi cancelado ou já foi usado. Peça um novo a quem convidou você."
            >
                <Head title="Convite indisponível" />
                <div className="text-center text-sm">
                    <TextLink href={route('login')}>Ir para o login</TextLink>
                </div>
            </AuthLayout>
        );
    }

    const accept: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('invitations.accept', invitation.token));
    };

    const description = `${invitation.invited_by ?? 'Alguém'} convidou você para atender os clientes de ${invitation.tenant} como ${invitation.role_label}.`;

    return (
        <AuthLayout title={`Equipe de ${invitation.tenant}`} description={description}>
            <Head title="Convite para a equipe" />

            {state === 'ready' && (
                <form onSubmit={accept} className="flex flex-col gap-4">
                    <Button type="submit" className="w-full" disabled={form.processing}>
                        {form.processing && <LoaderCircle className="size-4 animate-spin" />}
                        Aceitar convite
                    </Button>
                    <InputError message={(form.errors as Record<string, string>).invitation} className="text-center" />
                </form>
            )}

            {state === 'guest' && (
                <div className="flex flex-col gap-4">
                    <p className="text-muted-foreground text-center text-sm">
                        O convite foi enviado para <strong className="text-foreground">{invitation.email}</strong>. Entre ou crie uma conta com esse
                        e-mail.
                    </p>
                    <Button asChild className="w-full">
                        <Link href={route('login')}>Entrar</Link>
                    </Button>
                    <Button asChild variant="outline" className="w-full">
                        <Link href={route('register', { email: invitation.email })}>Criar conta</Link>
                    </Button>
                </div>
            )}

            {state === 'wrong_account' && (
                <div className="flex flex-col gap-4 text-center text-sm">
                    <p className="text-muted-foreground">
                        Este convite foi enviado para <strong className="text-foreground">{invitation.email}</strong>, mas você entrou com outra
                        conta.
                    </p>
                    <Button asChild variant="outline" className="w-full">
                        <Link href={route('logout')} method="post" as="button">
                            Sair e entrar com {invitation.email}
                        </Link>
                    </Button>
                </div>
            )}
        </AuthLayout>
    );
}
