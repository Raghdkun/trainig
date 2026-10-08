import { ListChecks, Target } from 'lucide-react';
import { ChecklistSections } from '@/components/training/checklist-sections';
import { CompletionBar } from '@/components/training/completion-bar';
import { DevelopmentPlanPicker } from '@/components/training/development-plan-picker';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import type {
    DevelopmentPickerSection,
    DevelopmentPlanData,
    SkillRating,
} from '@/types/training';

export function DevelopmentPlanPanel({
    traineeId,
    traineeName,
    plan,
    picker,
    skillRatings = [],
    readOnly,
}: {
    traineeId: number;
    traineeName: string;
    plan: DevelopmentPlanData;
    picker: DevelopmentPickerSection[];
    skillRatings?: SkillRating[];
    readOnly: boolean;
}) {
    const selectedIds = plan.sections.flatMap((section) =>
        section.categories.flatMap((category) =>
            category.items.map((item) => item.id),
        ),
    );
    const isEmpty = plan.sections.length === 0;

    const planPicker = (label: string) => (
        <DevelopmentPlanPicker
            traineeId={traineeId}
            sections={picker}
            selectedIds={selectedIds}
            skillRatings={skillRatings}
            trigger={
                <Button variant="outline" size="sm">
                    <ListChecks className="size-4" /> {label}
                </Button>
            }
        />
    );

    if (isEmpty) {
        return (
            <Card className="flex flex-col items-center justify-center gap-3 border-dashed p-10 text-center shadow-none">
                <Target className="size-8 text-muted-foreground" />
                <div className="space-y-1">
                    <p className="text-sm font-medium">No plan yet</p>
                    <p className="max-w-md text-sm text-muted-foreground">
                        {readOnly
                            ? 'An admin will pick the specific tasks this employee should focus on.'
                            : 'Pick a few specific tasks from the curriculum to focus on. Saving the plan makes it Active.'}
                    </p>
                </div>
                {!readOnly && planPicker('Build plan')}
            </Card>
        );
    }

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="max-w-xs flex-1 space-y-1">
                    <p className="text-xs text-muted-foreground">
                        {plan.stats.completed} of {plan.stats.total} plan items
                        complete
                    </p>
                    <CompletionBar
                        completed={plan.stats.completed}
                        total={plan.stats.total}
                    />
                </div>
                {!readOnly && planPicker('Edit plan')}
            </div>

            <ChecklistSections
                sections={plan.sections}
                traineeId={traineeId}
                traineeName={traineeName}
                readOnly={readOnly}
            />
        </div>
    );
}
