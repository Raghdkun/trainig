import { Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    // BookOpen,
    Building2,
    Archive,
    ClipboardList,
    FileQuestion,
    // FolderGit2,
    LayoutGrid,
    TrendingUp,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { StoreSwitcher } from '@/components/store-switcher';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { management } from '@/routes/admin';
import { index as developmentZoneIndex } from '@/routes/development-zone';
import { index as reportsIndex } from '@/routes/reports';
import { index as traineesIndex } from '@/routes/trainees';
import { index as archiveRequestsIndex } from '@/routes/training/archive-requests';
import { index as quizResultsIndex } from '@/routes/training/quiz-results';
import { index as sectionsIndex } from '@/routes/training/sections';
import type { Auth, NavItem } from '@/types';

const footerNavItems: NavItem[] = [];

export function AppSidebar() {
    const { auth, pendingArchiveRequests } = usePage<{
        auth: Auth;
        pendingArchiveRequests: number | null;
    }>().props;

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: 'Trainees',
            href: traineesIndex(),
            icon: Users,
        },
        {
            title: 'Development Zone',
            href: developmentZoneIndex(),
            icon: TrendingUp,
        },
        {
            title: 'Reports',
            href: reportsIndex(),
            icon: BarChart3,
        },
    ];

    if (auth.user.role === 'super_admin') {
        mainNavItems.push(
            {
                title: 'Content Builder',
                href: sectionsIndex(),
                icon: ClipboardList,
            },
            {
                title: 'Management',
                href: management(),
                icon: Building2,
            },
            {
                title: 'Quiz Results',
                href: quizResultsIndex(),
                icon: FileQuestion,
            },
            {
                title: 'Archive Requests',
                href: archiveRequestsIndex(),
                icon: Archive,
                badge: pendingArchiveRequests || null,
            },
        );
    }

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                    <StoreSwitcher />
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
