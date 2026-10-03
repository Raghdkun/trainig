import type { Auth } from '@/types/auth';
import type { StoreSwitcherContext } from '@/types/training';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            storeSwitcher: StoreSwitcherContext | null;
            [key: string]: unknown;
        };
    }
}
