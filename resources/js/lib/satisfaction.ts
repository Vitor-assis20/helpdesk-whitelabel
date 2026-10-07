/** Notas da avaliação do atendimento (mesmas do enum SatisfactionScore). */
export const SCORES = [
    { value: 1, label: 'Péssimo', emoji: '😞' },
    { value: 2, label: 'Ruim', emoji: '🙁' },
    { value: 3, label: 'Regular', emoji: '😐' },
    { value: 4, label: 'Bom', emoji: '🙂' },
    { value: 5, label: 'Ótimo', emoji: '😄' },
] as const;

export interface Rating {
    score: number;
    label: string;
    comment: string | null;
}

export function scoreEmoji(score: number): string {
    return SCORES.find((item) => item.value === score)?.emoji ?? '';
}
