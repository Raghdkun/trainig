import type { EvaluationGrade } from '@/types/training';

/**
 * Color tones for the manager's A–D grade — A is the strongest (green),
 * D the weakest (red). Kept to a border and text accent, never a fill, so
 * the grade reads at a glance without adding visual noise.
 */
export const GRADE_TONES: Record<EvaluationGrade, { selected: string }> = {
    A: {
        selected:
            'border-emerald-500/70 text-emerald-600 dark:text-emerald-400',
    },
    B: {
        selected: 'border-blue-500/70 text-blue-600 dark:text-blue-400',
    },
    C: {
        selected: 'border-amber-500/70 text-amber-600 dark:text-amber-400',
    },
    D: {
        selected: 'border-red-500/70 text-red-600 dark:text-red-400',
    },
};
