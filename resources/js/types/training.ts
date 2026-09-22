export type Importance =
    | 'optional'
    | 'moderately_important'
    | 'highly_important';

export type MediaType = 'link' | 'file' | 'image' | 'video';

/** Upload constraints shared from the server (see MediaType::uploadLimits). */
export type MediaUploadLimit = { max_kb: number; accept: string };

export type MediaLimits = Partial<Record<MediaType, MediaUploadLimit>>;

export type MediaItem = {
    id: number;
    checklist_item_id: number;
    type: MediaType;
    url: string | null;
    path: string | null;
    label: string | null;
    order: number;
    display_url: string | null;
};

export type ChecklistItem = {
    id: number;
    category_id: number;
    parent_id: number | null;
    title: string;
    content: string | null;
    importance: Importance | null;
    requires_rating: boolean;
    order: number;
    children?: ChecklistItem[];
    media?: MediaItem[];
};

export type Category = {
    id: number;
    section_id: number;
    title: string;
    description: string | null;
    color: string | null;
    order: number;
    items?: ChecklistItem[];
};

export type SectionStatus = 'draft' | 'published';

export type Section = {
    id: number;
    title: string;
    description: string | null;
    icon: string | null;
    order: number;
    status: SectionStatus;
    pie_content_review: string | null;
    screen_to_shoulder: string | null;
    hands_on_shifts: string | null;
    categories?: Category[];
    categories_count?: number;
    checklist_items_count?: number;
    quiz?: Quiz | null;
};

export type SectionStatusCounts = {
    all: number;
    draft: number;
    published: number;
};

export type QuizQuestionOption = {
    id: number;
    text: string;
    is_correct: boolean;
    order: number;
};

export type QuizQuestionType = 'single' | 'multi';

export type QuizQuestion = {
    id: number;
    prompt: string;
    type: QuizQuestionType;
    order: number;
    options: QuizQuestionOption[];
};

/** A station's quiz as authored in the Content Builder. */
export type Quiz = {
    id: number;
    section_id: number;
    questions: QuizQuestion[];
};

export type MoveTarget = {
    id: number;
    title: string;
    categories: { id: number; title: string }[];
};

export const IMPORTANCE_OPTIONS: { value: Importance; label: string }[] = [
    { value: 'optional', label: 'Optional' },
    { value: 'moderately_important', label: 'Moderately important' },
    { value: 'highly_important', label: 'Highly important' },
];

export type StoreOption = { id: number; name: string };

export type StoreSwitcherContext = {
    canChoose: boolean;
    options: StoreOption[];
};

export type TraineeStats = {
    completed: number;
    total: number;
    average_rating: number | null;
};

export type TraineeSummary = {
    id: number;
    name: string;
    position: string | null;
    store: StoreOption;
    stats: TraineeStats;
};

export type TraineeDetail = {
    id: number;
    name: string;
    position: string | null;
    hired_at: string | null;
    store: StoreOption;
    managers: { id: number; name: string }[];
    archived_at: string | null;
    archived_by: { id: number; name: string } | null;
};

export type TraineeStatusCounts = {
    active: number;
    archived: number;
};

export type EvaluationState = {
    completed: boolean;
    rating: number | null;
    notes: string | null;
};

export type EvaluationItem = {
    id: number;
    category_id: number;
    parent_id: number | null;
    title: string;
    content: string | null;
    importance: Importance | null;
    requires_rating: boolean;
    order: number;
    media: MediaItem[];
    children: EvaluationItem[];
    evaluation: EvaluationState | null;
};

export type ProgressCategory = {
    id: number;
    title: string;
    description: string | null;
    color: string | null;
    average_rating: number | null;
    items: EvaluationItem[];
};

export type QuizAttemptStatus = 'sent' | 'completed';

/**
 * A section's quiz status as seen from the trainee page — deliberately
 * never carries a score or answers (see TraineeProgress::quizStatus). Safe
 * for any manager who can view this trainee, not just admins.
 */
export type SectionQuiz = {
    id: number;
    questions_count: number;
    attempt: {
        status: QuizAttemptStatus;
        link: string | null;
        flagged: boolean;
    } | null;
};

export type ProgressSection = {
    id: number;
    title: string;
    description: string | null;
    icon: string | null;
    pie_content_review: string | null;
    screen_to_shoulder: string | null;
    hands_on_shifts: string | null;
    average_rating: number | null;
    categories: ProgressCategory[];
    quiz: SectionQuiz | null;
};

export type TraineeProgressData = {
    sections: ProgressSection[];
    currentStepId: number | null;
    stats: TraineeStats;
};

export type RoleValue = 'super_admin' | 'manager';

export type RoleOption = { value: RoleValue; label: string };

export type AdminUserRow = {
    id: number;
    name: string;
    email: string;
    role: RoleValue;
    stores: StoreOption[];
    joined: string | null;
};

export type AdminStoreRow = {
    id: number;
    name: string;
    address: string | null;
    managers_count: number;
    trainees_count: number;
};

export type DashboardStats = {
    users: number;
    stores: number;
    trainees: number;
    sections: number;
    items: number;
};

export type ManagerStats = {
    trainees: number;
    completion: number;
    average_rating: number | null;
};

export type DevelopmentPickerItem = { id: number; title: string };

export type DevelopmentPickerCategory = {
    id: number;
    title: string;
    items: DevelopmentPickerItem[];
};

export type DevelopmentPickerSection = {
    id: number;
    title: string;
    categories: DevelopmentPickerCategory[];
};

export type DevelopmentStats = { completed: number; total: number };

export type DevelopmentPlanData = {
    sections: ProgressSection[];
    stats: DevelopmentStats;
};

export type DevelopmentStatus = 'pending' | 'active' | 'completed';

export const DEVELOPMENT_STATUS_LABELS: Record<DevelopmentStatus, string> = {
    pending: 'Pending',
    active: 'Active',
    completed: 'Completed',
};

export type DevelopmentCriterion = {
    id: number;
    label: string;
    description: string | null;
    order?: number;
    is_active?: boolean;
};

export type DevelopmentEvaluationRatingInput = {
    criterion_id: number;
    rating: number;
};

export type DevelopmentEvaluationSummary = {
    id: number;
    evaluator: { id: number; name: string } | null;
    notes: string | null;
    submitted_at: string;
    ratings: { criterion: DevelopmentCriterion; rating: number }[];
} | null;

export type DevelopmentZoneTrainee = {
    id: number;
    name: string;
    position: string | null;
    store: StoreOption;
    status: DevelopmentStatus;
    stats: DevelopmentStats;
};

export type AddableTrainee = {
    id: number;
    name: string;
    position: string | null;
    store: StoreOption;
};

export type DevelopmentZoneShowData = {
    trainee: {
        id: number;
        name: string;
        position: string | null;
        store: StoreOption;
        status: DevelopmentStatus;
        archived_at: string | null;
    };
    evaluation: DevelopmentEvaluationSummary;
    developmentPlan: DevelopmentPlanData;
    developmentPicker: DevelopmentPickerSection[];
    canManagePlan: boolean;
    canComplete: boolean;
    canRemove: boolean;
};

/** A row in the training-team-only Quiz Results list. */
export type QuizAttemptRow = {
    id: number;
    trainee: { id: number; name: string };
    store: StoreOption;
    section: { id: number; title: string };
    status: QuizAttemptStatus;
    flagged: boolean;
    score: number | null;
    sent_at: string;
    completed_at: string | null;
};

/** One question's full breakdown for the Quiz Results detail page. */
export type QuizResultOption = {
    id: number;
    text: string;
    is_correct: boolean;
    is_chosen: boolean;
};

export type QuizResultQuestion = {
    id: number;
    prompt: string;
    type: QuizQuestionType;
    options: QuizResultOption[];
};

export type QuizResultDetail = {
    id: number;
    trainee: { id: number; name: string };
    section: { id: number; title: string };
    status: QuizAttemptStatus;
    score: number | null;
    sent_at: string;
    completed_at: string | null;
};
