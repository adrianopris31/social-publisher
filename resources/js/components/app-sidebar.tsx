import { Link } from '@inertiajs/react';
import { Film, LayoutGrid, Link2, Send, SquarePen } from 'lucide-react';
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
import { index as socialAccountsIndex } from '@/routes/social-accounts';
import { create as createPost, index as postsIndex } from '@/routes/posts';
import { index as videosIndex } from '@/routes/videos';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'New post',
        href: createPost(),
        icon: SquarePen,
    },
    {
        title: 'Posts',
        href: postsIndex(),
        icon: Send,
    },
    {
        title: 'Videos',
        href: videosIndex(),
        icon: Film,
    },
    {
        title: 'Connected accounts',
        href: socialAccountsIndex(),
        icon: Link2,
    },
];

export function AppSidebar() {
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
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
