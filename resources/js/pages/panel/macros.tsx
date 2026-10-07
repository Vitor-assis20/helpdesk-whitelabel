import { Head, router, useForm } from '@inertiajs/react';
import { LoaderCircle, Pencil, Plus, Trash2 } from 'lucide-react';
import { type FormEventHandler, useRef, useState } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

interface StoredMacro {
    id: number;
    title: string;
    body: string;
    uses: number;
}

type Variable = { name: string; label: string };

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Respostas prontas', href: '/painel/respostas' }];

function MacroForm({ macro, variables, onDone }: { macro?: StoredMacro; variables: Variable[]; onDone: () => void }) {
    const form = useForm({ title: macro?.title ?? '', body: macro?.body ?? '' });
    const bodyRef = useRef<HTMLTextAreaElement>(null);

    // Insere a variável onde está o cursor.
    const insert = (name: string) => {
        const field = bodyRef.current;
        const tag = `{{${name}}}`;
        const start = field?.selectionStart ?? form.data.body.length;
        const end = field?.selectionEnd ?? start;
        form.setData('body', form.data.body.slice(0, start) + tag + form.data.body.slice(end));
        requestAnimationFrame(() => {
            field?.focus();
            field?.setSelectionRange(start + tag.length, start + tag.length);
        });
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: onDone };
        if (macro) form.put(route('panel.macros.update', macro.id), options);
        else form.post(route('panel.macros.store'), options);
    };

    return (
        <form onSubmit={submit} className="space-y-3 rounded-xl border p-4">
            <div className="grid gap-2">
                <Label htmlFor={`title-${macro?.id ?? 'new'}`}>Título</Label>
                <Input
                    id={`title-${macro?.id ?? 'new'}`}
                    required
                    maxLength={80}
                    placeholder="Ex.: Prazo de entrega"
                    value={form.data.title}
                    onChange={(e) => form.setData('title', e.target.value)}
                />
                <InputError message={form.errors.title} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={`body-${macro?.id ?? 'new'}`}>Texto</Label>
                <textarea
                    id={`body-${macro?.id ?? 'new'}`}
                    ref={bodyRef}
                    required
                    maxLength={10000}
                    className="border-input placeholder:text-muted-foreground focus-visible:ring-ring/50 min-h-32 w-full resize-y rounded-md border bg-transparent px-3 py-2 text-sm outline-none focus-visible:ring-[3px]"
                    placeholder="Olá, {{cliente.primeiro_nome}}! ..."
                    value={form.data.body}
                    onChange={(e) => form.setData('body', e.target.value)}
                />
                <InputError message={form.errors.body} />
                <div className="flex flex-wrap gap-1.5">
                    {variables.map((variable) => (
                        <button
                            key={variable.name}
                            type="button"
                            title={variable.label}
                            onClick={() => insert(variable.name)}
                            className="bg-muted hover:bg-accent rounded px-2 py-0.5 font-mono text-xs"
                        >
                            {`{{${variable.name}}}`}
                        </button>
                    ))}
                </div>
            </div>
            <div className="flex items-center gap-2">
                <Button type="submit" disabled={form.processing}>
                    {form.processing && <LoaderCircle className="size-4 animate-spin" />}
                    {macro ? 'Salvar' : 'Criar resposta'}
                </Button>
                <Button type="button" variant="ghost" onClick={onDone}>
                    Cancelar
                </Button>
            </div>
        </form>
    );
}

export default function Macros({ macros, variables }: { macros: StoredMacro[]; variables: Variable[] }) {
    // "new", o id da resposta em edição, ou null.
    const [editing, setEditing] = useState<number | 'new' | null>(macros.length === 0 ? 'new' : null);

    const remove = (macro: StoredMacro) => {
        if (confirm(`Excluir a resposta "${macro.title}"?`)) {
            router.delete(route('panel.macros.destroy', macro.id), { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Respostas prontas" />
            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">Respostas prontas</h1>
                        <p className="text-muted-foreground text-sm">
                            Textos que a equipe insere com <kbd className="bg-muted rounded px-1 font-mono">/</kbd> ao responder. As variáveis são
                            preenchidas com os dados do chamado.
                        </p>
                    </div>
                    {editing !== 'new' && (
                        <Button onClick={() => setEditing('new')}>
                            <Plus className="size-4" /> Nova resposta
                        </Button>
                    )}
                </div>

                {editing === 'new' && <MacroForm variables={variables} onDone={() => setEditing(null)} />}

                {macros.length > 0 && (
                    <ul className="space-y-3">
                        {macros.map((macro) =>
                            editing === macro.id ? (
                                <li key={macro.id}>
                                    <MacroForm macro={macro} variables={variables} onDone={() => setEditing(null)} />
                                </li>
                            ) : (
                                <li key={macro.id} className="flex items-start gap-3 rounded-xl border p-4">
                                    <div className="min-w-0 flex-1">
                                        <p className="font-medium">{macro.title}</p>
                                        <p className="text-muted-foreground line-clamp-3 text-sm whitespace-pre-wrap">{macro.body}</p>
                                        <p className="text-muted-foreground mt-1 text-xs">
                                            {macro.uses === 0 ? 'Ainda não usada' : `Usada ${macro.uses} ${macro.uses === 1 ? 'vez' : 'vezes'}`}
                                        </p>
                                    </div>
                                    <Button variant="ghost" size="icon" aria-label={`Editar ${macro.title}`} onClick={() => setEditing(macro.id)}>
                                        <Pencil className="size-4" />
                                    </Button>
                                    <Button variant="ghost" size="icon" aria-label={`Excluir ${macro.title}`} onClick={() => remove(macro)}>
                                        <Trash2 className="size-4" />
                                    </Button>
                                </li>
                            ),
                        )}
                    </ul>
                )}
            </div>
        </AppLayout>
    );
}
