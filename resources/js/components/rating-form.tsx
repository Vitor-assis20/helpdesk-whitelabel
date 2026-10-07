import { useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { type FormEventHandler, useEffect, useRef, useState } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { SCORES, scoreEmoji } from '@/lib/satisfaction';
import { cn } from '@/lib/utils';
import { type PortalTicket } from '@/types/portal';

/**
 * Avaliação do atendimento no portal. Vindo do e-mail (?nota=4), a nota já
 * chega marcada e a tela rola até aqui; gravar exige o clique em "Enviar".
 */
export function RatingForm({ ticket, suggestedScore }: { ticket: PortalTicket; suggestedScore: number | null }) {
    // Clicar numa nota do e-mail abre o formulário mesmo que o chamado já tenha avaliação.
    const [editing, setEditing] = useState(ticket.rating === null || suggestedScore !== null);
    const form = useForm({ score: suggestedScore ?? ticket.rating?.score ?? 0, comment: ticket.rating?.comment ?? '' });
    const section = useRef<HTMLElement>(null);

    useEffect(() => {
        if (suggestedScore) section.current?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }, [suggestedScore]);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('portal.tickets.rate', { ticket: ticket.token }), { preserveScroll: true, onSuccess: () => setEditing(false) });
    };

    if (ticket.rating && !editing) {
        return (
            <section id="avaliacao" ref={section} className="bg-background space-y-1 rounded-lg border p-4 text-sm">
                <p className="font-medium">Obrigado pela avaliação!</p>
                <p className="text-muted-foreground">
                    Você achou o atendimento {scoreEmoji(ticket.rating.score)} {ticket.rating.label.toLowerCase()}.{' '}
                    <button type="button" className="underline" onClick={() => setEditing(true)}>
                        Mudar avaliação
                    </button>
                </p>
            </section>
        );
    }

    return (
        <section id="avaliacao" ref={section} className="bg-background rounded-lg border p-4">
            <form onSubmit={submit} className="space-y-3">
                <div>
                    <h2 className="font-semibold">Como foi o atendimento?</h2>
                    <p className="text-muted-foreground text-sm">Sua opinião ajuda a equipe a melhorar.</p>
                </div>
                <div role="radiogroup" aria-label="Nota do atendimento" className="grid grid-cols-5 gap-2">
                    {SCORES.map((score) => (
                        <button
                            key={score.value}
                            type="button"
                            role="radio"
                            aria-checked={form.data.score === score.value}
                            onClick={() => form.setData('score', score.value)}
                            className={cn(
                                'flex flex-col items-center gap-1 rounded-lg border p-2 text-xs transition-colors',
                                form.data.score === score.value ? 'border-primary bg-primary/10 font-medium' : 'hover:bg-muted',
                            )}
                        >
                            <span className="text-2xl" aria-hidden>
                                {score.emoji}
                            </span>
                            {score.label}
                        </button>
                    ))}
                </div>
                <InputError message={form.errors.score} />
                <textarea
                    aria-label="Comentário (opcional)"
                    maxLength={1000}
                    className="placeholder:text-muted-foreground min-h-20 w-full resize-y rounded-md border bg-transparent px-3 py-2 text-sm outline-none"
                    placeholder="Quer contar mais alguma coisa? (opcional)"
                    value={form.data.comment}
                    onChange={(e) => form.setData('comment', e.target.value)}
                />
                <InputError message={form.errors.comment} />
                <Button type="submit" disabled={form.processing || form.data.score === 0}>
                    {form.processing && <LoaderCircle className="size-4 animate-spin" />}
                    Enviar avaliação
                </Button>
            </form>
        </section>
    );
}
