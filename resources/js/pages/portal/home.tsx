import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { type FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import TenantLayout from '@/layouts/tenant-layout';

type OpenTicketForm = { name: string; email: string; subject: string; body: string };

export default function PortalHome() {
    const { data, setData, post, processing, errors } = useForm<OpenTicketForm>({ name: '', email: '', subject: '', body: '' });
    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('portal.tickets.store'));
    };

    return (
        <TenantLayout>
            <Head title="Atendimento" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold">Como podemos ajudar?</h1>
                    <p className="text-muted-foreground">
                        Conte o que aconteceu. Você recebe o link da conversa por e-mail e pode acompanhar as respostas por aqui.
                    </p>
                </div>

                <form onSubmit={submit} className="bg-background space-y-4 rounded-lg border p-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Nome</Label>
                            <Input id="name" required autoComplete="name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                            <InputError message={errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="email">E-mail</Label>
                            <Input
                                id="email"
                                type="email"
                                required
                                autoComplete="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                            />
                            <InputError message={errors.email} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="subject">Assunto</Label>
                        <Input id="subject" required maxLength={200} value={data.subject} onChange={(e) => setData('subject', e.target.value)} />
                        <InputError message={errors.subject} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="body">Mensagem</Label>
                        <textarea
                            id="body"
                            required
                            rows={6}
                            className="border-input bg-background focus-visible:ring-ring rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:outline-hidden"
                            value={data.body}
                            onChange={(e) => setData('body', e.target.value)}
                        />
                        <InputError message={errors.body} />
                    </div>

                    <Button type="submit" className="w-full" disabled={processing}>
                        {processing && <LoaderCircle className="size-4 animate-spin" />}
                        Enviar
                    </Button>
                </form>
            </div>
        </TenantLayout>
    );
}
