<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Banknote, Clock, HandCoins, UserCheck, Users } from 'lucide-vue-next';

// CHANGED: replaced starter-kit placeholder dashboard with live SACCO stats

interface RecentMember {
    id: number;
    member_no: string;
    full_name: string;
    status: string;
    created_at: string;
}

defineProps<{
    stats: {
        members_total: number;
        members_active: number;
        members_pending: number;
        savings_balance: number | null;
        loans_outstanding: number | null;
    };
    recentMembers: RecentMember[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

const statusClasses: Record<string, string> = {
    pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    active: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    dormant: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
    exited: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
};

const ugx = (value: number | null) =>
    value === null ? '—' : new Intl.NumberFormat('en-UG', { style: 'currency', currency: 'UGX', maximumFractionDigits: 0 }).format(value);
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <!-- Stat cards -->
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <div class="flex items-center gap-2 text-sm text-muted-foreground"><Users class="h-4 w-4" /> Total members</div>
                    <div class="mt-2 text-2xl font-semibold">{{ stats.members_total }}</div>
                </div>
                <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <div class="flex items-center gap-2 text-sm text-muted-foreground"><UserCheck class="h-4 w-4" /> Active members</div>
                    <div class="mt-2 text-2xl font-semibold">{{ stats.members_active }}</div>
                </div>
                <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <div class="flex items-center gap-2 text-sm text-muted-foreground"><Clock class="h-4 w-4" /> Pending approval</div>
                    <div class="mt-2 text-2xl font-semibold">{{ stats.members_pending }}</div>
                </div>
                <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <div class="flex items-center gap-2 text-sm text-muted-foreground"><Banknote class="h-4 w-4" /> Savings balance</div>
                    <div class="mt-2 text-2xl font-semibold">{{ ugx(stats.savings_balance) }}</div>
                    <div class="text-xs text-muted-foreground">Available after Savings module</div>
                </div>
                <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <div class="flex items-center gap-2 text-sm text-muted-foreground"><HandCoins class="h-4 w-4" /> Loans outstanding</div>
                    <div class="mt-2 text-2xl font-semibold">{{ ugx(stats.loans_outstanding) }}</div>
                    <div class="text-xs text-muted-foreground">Available after Loans module</div>
                </div>
            </div>

            <!-- Recent members -->
            <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <div class="flex items-center justify-between border-b px-4 py-3">
                    <h2 class="font-medium">Recent members</h2>
                    <Link href="/members/create">
                        <Button size="sm">New Member</Button>
                    </Link>
                </div>
                <table class="w-full text-sm">
                    <tbody>
                        <tr
                            v-for="member in recentMembers"
                            :key="member.id"
                            class="cursor-pointer border-b transition-colors last:border-0 hover:bg-muted/50"
                            @click="$inertia.visit(`/members/${member.id}`)"
                        >
                            <td class="px-4 py-3 font-mono">{{ member.member_no }}</td>
                            <td class="px-4 py-3">{{ member.full_name }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="statusClasses[member.status]">
                                    {{ member.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right text-muted-foreground">{{ member.created_at }}</td>
                        </tr>
                        <tr v-if="recentMembers.length === 0">
                            <td class="px-4 py-10 text-center text-muted-foreground">
                                No members yet —
                                <Link href="/members/create" class="underline">register the first member</Link>.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
