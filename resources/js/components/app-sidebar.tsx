import { Link, usePage } from '@inertiajs/react';
import { LayoutGrid, Printer, ScrollText, Server, Users } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
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
import { index as adminJobs } from '@/routes/admin/jobs';
import { index as adminUsers } from '@/routes/admin/users';
import { create, jobs } from '@/routes/print';
import type { Auth, NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Tableau de bord',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Imprimer',
        href: create(),
        icon: Printer,
    },
    {
        title: 'Mes impressions',
        href: jobs(),
        icon: ScrollText,
    },
];

const adminNavItems: NavItem[] = [
    {
        title: 'Membres',
        href: adminUsers(),
        icon: Users,
    },
    {
        title: 'Toutes les impressions',
        href: adminJobs(),
        icon: Server,
    },
];

export function AppSidebar() {
    const auth = usePage().props.auth as Auth | undefined;

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
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />

                {auth?.user?.is_admin === true && (
                    <NavMain items={adminNavItems} label="Administration" />
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
