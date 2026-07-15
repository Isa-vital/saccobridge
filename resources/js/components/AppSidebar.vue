<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
// CHANGED: BookOpen/Folder icons removed with starter-kit footer links
import { Banknote, BookText, CalendarCheck, CheckSquare, LayoutGrid, ListTree, PiggyBank, Scale, Users, Wallet } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Members',
        href: '/members',
        icon: Users,
    },
    {
        title: 'Teller Station',
        href: '/savings/teller',
        icon: Banknote,
    },
    {
        title: 'Savings Accounts',
        href: '/savings/accounts',
        icon: PiggyBank,
    },
    {
        title: 'Approvals',
        href: '/savings/approvals',
        icon: CheckSquare,
    },
    {
        title: 'Teller Sessions',
        href: '/savings/sessions',
        icon: Wallet,
    },
    {
        title: 'Savings Products',
        href: '/savings/products',
        icon: PiggyBank,
    },
    {
        title: 'Journal',
        href: '/gl/journal',
        icon: BookText,
    },
    {
        title: 'Chart of Accounts',
        href: '/gl/accounts',
        icon: ListTree,
    },
    {
        title: 'Trial Balance',
        href: '/gl/trial-balance',
        icon: Scale,
    },
    {
        title: 'Periods',
        href: '/gl/periods',
        icon: CalendarCheck,
    },
];

const footerNavItems: NavItem[] = [
    // CHANGED: removed starter-kit Github/Docs links — not relevant to SACCO staff
    // {
    //     title: 'Github Repo',
    //     href: 'https://github.com/laravel/vue-starter-kit',
    //     icon: Folder,
    // },
    // {
    //     title: 'Documentation',
    //     href: 'https://laravel.com/docs/starter-kits',
    //     icon: BookOpen,
    // },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
