export type Importance = 'optional' | 'moderately_important' | 'highly_important';

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

export type Section = {
    id: number;
    title: string;
    description: string | null;
    icon: string | null;
    order: number;
    pie_content_review: string | null;
    screen_to_shoulder: string | null;
    hands_on_shifts: string | null;
    categories?: Category[];
    categories_count?: number;
    checklist_items_count?: number;
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
