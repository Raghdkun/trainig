export type ReportOverview = {
    trainees: number;
    completion: number;
    average_score: number | null;
    fully_trained: number;
    at_risk: number;
    in_progress: number;
    not_started: number;
    evaluations_recorded: number;
};

export type TrendPoint = { week: string; label: string; count: number };

export type DistributionBand = { band: string; count: number };

export type StorePerformanceRow = {
    id: number;
    name: string;
    trainees: number;
    completion: number;
    average_score: number | null;
};

export type ManagerActivityRow = {
    id: number;
    name: string;
    store: string | null;
    assigned_trainees: number;
    evaluations_recorded: number;
    average_score: number | null;
};

export type TraineeStatusValue =
    | 'not_started'
    | 'in_progress'
    | 'completed'
    | 'at_risk';

export type TraineeStatusRow = {
    id: number;
    name: string;
    position: string | null;
    store: string;
    status: TraineeStatusValue;
    completion: number;
    average_score: number | null;
    last_activity: string | null;
};

export type TraineeStatusReport = {
    rows: TraineeStatusRow[];
    onboarding_days_avg: number | null;
};

export type StationSectionRow = {
    id: number;
    title: string;
    average_score: number | null;
    completion: number;
};

export type StationCategoryRow = {
    id: number;
    title: string;
    section_title: string;
    average_score: number | null;
    completion: number;
};

export type ProblemItemRow = {
    id: number;
    title: string;
    category_title: string;
    section_title: string;
    average_score: number;
    evaluations: number;
};

export type StationInsights = {
    sections: StationSectionRow[];
    categories: StationCategoryRow[];
    problem_items: ProblemItemRow[];
};

export type ImportanceRow = {
    importance: string | null;
    label: string;
    average_score: number | null;
    completion: number;
    evaluations: number;
};

export type DevelopmentZoneSummary = {
    pending: number;
    active: number;
    completed: number;
    in_zone: number;
    average_evaluation_rating: number | null;
    plan_completion: number;
};

export type QuizResultsSummary = {
    sent: number;
    completed: number;
    average_score: number | null;
};

export type ReportFilters = {
    store: number | null;
    weeks: number;
    includeArchived: boolean;
};

export type ReportArea = 'overview' | 'stores' | 'trainees' | 'content';
