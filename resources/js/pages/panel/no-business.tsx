import { Head, useForm } from '@inertiajs/react';
import { Headset, LoaderCircle } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';

/** "Loja Aurora Ltda." → "loja-aurora-ltda" (mesma regra do servidor: minúsculas, dígitos e hífens). */
function slugify(name: string): string {
    return name
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 63)
        .replace(/-+$/, '');
}

export default function NoBusiness({ domain }: { domain: string }) {
    const form = useForm({ name: '', slug: '' });
    // Enquanto a pessoa não mexe no endereço, ele acompanha o nome.
    const [slugEdited, setSlugEdited] = useState(false);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('panel.tenants.store'));
    };

    return (
        <AppLayout>
            <Head title="Nenhuma equipe" />

            <div className="flex flex-1 flex-col items-center justify-center gap-8 p-8">
                <div className="flex flex-col items-center gap-3 text-center">
                    <Headset className="text-muted-foreground size-10" />
                    <h1 className="text-xl font-semibold">Você ainda não faz parte de nenhuma equipe</h1>
                    <p className="text-muted-foreground max-w-md">
                        Foi convidado? Abra o link do e-mail de convite. Ou cadastre sua empresa para começar a atender.
                    </p>
                </div>

                <form onSubmit={submit} className="w-full max-w-md space-y-4 rounded-xl border p-5">
                    <h2 className="font-semibold">Cadastrar empresa</h2>
                    <div className="grid gap-2">
                        <Label htmlFor="name">Nome da empresa</Label>
                        <Input
                            id="name"
                            required
                            maxLength={100}
                            value={form.data.name}
                            onChange={(e) => form.setData({ name: e.target.value, slug: slugEdited ? form.data.slug : slugify(e.target.value) })}
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="slug">Endereço do portal</Label>
                        <div className="flex items-center gap-2">
                            <Input
                                id="slug"
                                required
                                maxLength={63}
                                className="font-mono"
                                value={form.data.slug}
                                onChange={(e) => {
                                    setSlugEdited(true);
                                    form.setData('slug', e.target.value.toLowerCase());
                                }}
                            />
                            <span className="text-muted-foreground shrink-0 text-sm">.{domain}</span>
                        </div>
                        <InputError message={form.errors.slug} />
                    </div>
                    <Button type="submit" className="w-full" disabled={form.processing}>
                        {form.processing && <LoaderCircle className="size-4 animate-spin" />}
                        Cadastrar e virar dono
                    </Button>
                </form>
            </div>
        </AppLayout>
    );
}
