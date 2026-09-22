import { ChevronDown, Clock } from 'lucide-react';
import { useState } from 'react';
import { CategorySection } from '@/components/training/category-section';
import { RatingMeter } from '@/components/training/rating-meter';
import { SectionQuizCard } from '@/components/training/section-quiz-card';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import type {
    EvaluationItem as EvaluationItemType,
    ProgressSection,
} from '@/types/training';

function leafCount(items: EvaluationItemType[]): {
    done: number;
    total: number;
} {
    return items.reduce(
        (acc, item) => {
            if (item.children.length > 0) {
                const child = leafCount(item.children);

                return {
                    done: acc.done + child.done,
                    total: acc.total + child.total,
                };
            }

            return {
                done: acc.done + (item.evaluation?.completed ? 1 : 0),
                total: acc.total + 1,
            };
        },
        { done: 0, total: 0 },
    );
}

function itemsContain(items: EvaluationItemType[], stepId: number): boolean {
    return items.some(
        (item) => item.id === stepId || itemsContain(item.children, stepId),
    );
}

/** The category holding the current step, so it can open by default. */
function categoryOfStep(
    sections: ProgressSection[],
    stepId: number | null,
): number | null {
    if (stepId === null) {
        return null;
    }

    for (const section of sections) {
        for (const category of section.categories) {
            if (itemsContain(category.items, stepId)) {
                return category.id;
            }
        }
    }

    return null;
}

/** The section holding the current step, so it can open by default. */
function sectionOfStep(
    sections: ProgressSection[],
    stepId: number | null,
): number | null {
    if (stepId === null) {
        return sections[0]?.id ?? null;
    }

    for (const section of sections) {
        if (
            section.categories.some((category) =>
                itemsContain(category.items, stepId),
            )
        ) {
            return section.id;
        }
    }

    return null;
}

/**
 * A trainee's checklist, grouped into collapsible Section → Category cards.
 * Shared by the standard trainee checklist and the Development Plan view, so
 * both present the exact same station/category experience.
 */
export function ChecklistSections({
    sections,
    traineeId,
    traineeName,
    readOnly = false,
    currentStepId = null,
}: {
    sections: ProgressSection[];
    traineeId: number;
    traineeName: string;
    readOnly?: boolean;
    currentStepId?: number | null;
}) {
    // Single-open accordions; the current step's section + category open first.
    const [openSectionId, setOpenSectionId] = useState<number | null>(() =>
        sectionOfStep(sections, currentStepId),
    );
    const [openCategoryId, setOpenCategoryId] = useState<number | null>(() =>
        categoryOfStep(sections, currentStepId),
    );

    return (
        <>
            {sections.map((section) => {
                const count = leafCount(
                    section.categories.flatMap((c) => c.items),
                );

                return (
                    <Card key={section.id} className="gap-0 py-0">
                        <Collapsible
                            open={openSectionId === section.id}
                            onOpenChange={(isOpen) =>
                                setOpenSectionId(isOpen ? section.id : null)
                            }
                        >
                            <CollapsibleTrigger className="group flex w-full items-center gap-3 p-4 text-left">
                                <ChevronDown className="size-4 shrink-0 text-muted-foreground transition-transform group-data-[state=closed]:-rotate-90" />
                                <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                                    <span className="truncate font-semibold">
                                        {section.title}
                                    </span>
                                    {section.hands_on_shifts && (
                                        <span className="flex items-center gap-1 text-xs text-muted-foreground">
                                            <Clock className="size-3 shrink-0" />
                                            Hands-on: {section.hands_on_shifts}
                                        </span>
                                    )}
                                </div>
                                {section.average_rating !== null && (
                                    <RatingMeter
                                        value={section.average_rating}
                                        size="sm"
                                        className="hidden shrink-0 sm:flex"
                                    />
                                )}
                                <Badge
                                    variant={
                                        count.done === count.total &&
                                        count.total > 0
                                            ? 'default'
                                            : 'secondary'
                                    }
                                >
                                    {count.done}/{count.total}
                                </Badge>
                            </CollapsibleTrigger>
                            <CollapsibleContent className="space-y-3 px-4 pb-4">
                                {section.categories.map((category) => (
                                    <CategorySection
                                        key={category.id}
                                        category={category}
                                        traineeId={traineeId}
                                        currentStepId={currentStepId}
                                        readOnly={readOnly}
                                        open={openCategoryId === category.id}
                                        onOpenChange={(isOpen) =>
                                            setOpenCategoryId(
                                                isOpen ? category.id : null,
                                            )
                                        }
                                    />
                                ))}
                                {section.quiz && (
                                    <SectionQuizCard
                                        traineeId={traineeId}
                                        traineeName={traineeName}
                                        quiz={section.quiz}
                                        readOnly={readOnly}
                                    />
                                )}
                            </CollapsibleContent>
                        </Collapsible>
                    </Card>
                );
            })}
        </>
    );
}
