import { MessageSquareText } from 'lucide-react';
import { type KeyboardEvent, useState } from 'react';

import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { type Macro } from '@/types/panel';

/** "/" no início de uma mensagem de uma linha abre a busca pelas respostas prontas. */
const SLASH = /^\/([^\n]*)$/;

function normalize(text: string): string {
    return text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
}

/**
 * Atalho "/" e menu de respostas prontas para o campo de resposta.
 * Escolher uma resposta substitui o "/busca" (ou o campo vazio); com texto já escrito, acrescenta ao final.
 */
export function useMacroComposer(macros: Macro[], body: string, onPick: (body: string, macro: Macro) => void) {
    const [active, setActive] = useState(0);
    const [dismissed, setDismissed] = useState<string | null>(null);

    const query = body.match(SLASH)?.[1];
    const matches = query === undefined ? [] : macros.filter((macro) => normalize(macro.title).includes(normalize(query.trim())));
    const open = matches.length > 0 && dismissed !== body;
    const current = Math.min(active, matches.length - 1);

    const pick = (macro: Macro) => {
        const replace = body.trim() === '' || SLASH.test(body);
        onPick(replace ? macro.body : `${body.trimEnd()}\n\n${macro.body}`, macro);
        setActive(0);
    };

    /** Teclado com a lista aberta: setas navegam, Enter/Tab escolhe, Esc fecha. */
    const onKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
        if (!open) return;

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            const step = event.key === 'ArrowDown' ? 1 : -1;
            setActive((current + step + matches.length) % matches.length);
        } else if (event.key === 'Enter' || event.key === 'Tab') {
            event.preventDefault();
            pick(matches[current]);
        } else if (event.key === 'Escape') {
            event.preventDefault();
            setDismissed(body);
        }
    };

    const suggestions = open ? (
        <ul role="listbox" aria-label="Respostas prontas" className="bg-popover max-h-60 overflow-y-auto rounded-lg border p-1 shadow-sm">
            {matches.map((macro, index) => (
                <li
                    key={macro.id}
                    role="option"
                    aria-selected={index === current}
                    // mousedown: escolhe antes de o campo perder o foco.
                    onMouseDown={(event) => {
                        event.preventDefault();
                        pick(macro);
                    }}
                    onMouseEnter={() => setActive(index)}
                    className={cn('cursor-pointer rounded-md px-3 py-2 text-sm', index === current && 'bg-accent')}
                >
                    <p className="font-medium">{macro.title}</p>
                    <p className="text-muted-foreground truncate text-xs">{macro.body}</p>
                </li>
            ))}
        </ul>
    ) : null;

    const menu =
        macros.length > 0 ? (
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button type="button" variant="ghost" size="sm">
                        <MessageSquareText className="size-4" /> Respostas prontas
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start" className="max-h-72 w-72 overflow-y-auto">
                    <DropdownMenuLabel className="text-muted-foreground text-xs font-normal">Dica: digite / no campo para buscar</DropdownMenuLabel>
                    {macros.map((macro) => (
                        <DropdownMenuItem key={macro.id} onSelect={() => pick(macro)}>
                            {macro.title}
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuContent>
            </DropdownMenu>
        ) : null;

    return { onKeyDown, suggestions, menu };
}
