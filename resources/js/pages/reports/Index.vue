<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import ExportButtons from '@/components/ExportButtons.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    summary: {
        as_of: string;
        members: { total: number; active: number; pending: number; exited: number };
        savings: { accounts: number; balance: string };
        shares: { shareholders: number; shares: number; capital: string };
        loans: { active: number; outstanding: string; in_arrears: number; provisions: string };
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Reports', href: '/reports' }];

const reports = [
    { title: 'Balance Sheet', description: 'Statement of financial position from the general ledger', href: '/reports/balance-sheet' },
    { title: 'Income Statement', description: 'Income and expenses for a period, net surplus', href: '/reports/income-statement' },
    { title: 'Portfolio at Risk (PAR)', description: 'UMRA loan aging, classification buckets and provisioning', href: '/reports/par' },
    { title: 'Savings Summary', description: 'Balances and account counts per savings product', href: '/reports/savings-summary' },
];

const ugx = (value: string) => new Intl.NumberFormat('en-UG').format(Number(value));
</script>

<template>
    <Head title="Reports" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <h1 class="text-xl font-semibold">UMRA Reports</h1>

            <!-- Institutional summary -->
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl border p-4">
                    <div class="text-sm text-muted-foreground">Members (active / total)</div>
                    <div class="mt-1 text-xl font-semibold">{{ summary.members.active }} / {{ summary.members.total }}</div>
                </div>
                <div class="rounded-xl border p-4">
                    <div class="text-sm text-muted-foreground">Savings ({{ summary.savings.accounts }} accounts)</div>
                    <div class="mt-1 font-mono text-xl font-semibold">{{ ugx(summary.savings.balance) }}</div>
                </div>
                <div class="rounded-xl border p-4">
                    <div class="text-sm text-muted-foreground">Share capital ({{ summary.shares.shareholders }} shareholders)</div>
                    <div class="mt-1 font-mono text-xl font-semibold">{{ ugx(summary.shares.capital) }}</div>
                </div>
                <div class="rounded-xl border p-4">
                    <div class="text-sm text-muted-foreground">Loan portfolio ({{ summary.loans.active }} active)</div>
                    <div class="mt-1 font-mono text-xl font-semibold">{{ ugx(summary.loans.outstanding) }}</div>
                    <div v-if="summary.loans.in_arrears > 0" class="text-xs text-red-600">{{ summary.loans.in_arrears }} in arrears</div>
                </div>
            </div>

            <!-- Report links -->
            <div class="grid gap-4 md:grid-cols-2">
                <Link v-for="report in reports" :key="report.href" :href="report.href" class="rounded-xl border p-4 transition-colors hover:bg-muted/50">
                    <div class="font-medium">{{ report.title }}</div>
                    <div class="text-sm text-muted-foreground">{{ report.description }}</div>
                </Link>
            </div>

            <!-- Member register export -->
            <div class="flex items-center justify-between rounded-xl border p-4">
                <div>
                    <div class="font-medium">Statutory Member Register</div>
                    <div class="text-sm text-muted-foreground">Full member list as required by UMRA — export only</div>
                </div>
                <ExportButtons report="member-register" />
            </div>
        </div>
    </AppLayout>
</template>
