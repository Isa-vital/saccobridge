<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import ExportButtons from '@/components/ExportButtons.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

interface Bucket {
    classification: string;
    provision_rate: string;
    loans: number;
    outstanding: string;
    provision: string;
}

defineProps<{
    report: {
        as_of: string;
        buckets: Bucket[];
        totals: { gross_portfolio: string; portfolio_at_risk: string; par_ratio: string; total_provision: string; net_portfolio: string };
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Reports', href: '/reports' },
    { title: 'Portfolio at Risk', href: '/reports/par' },
];

const ugx = (value: string) => new Intl.NumberFormat('en-UG', { minimumFractionDigits: 2 }).format(Number(value));

const rowClasses: Record<string, string> = {
    performing: '',
    watch: 'bg-yellow-50 dark:bg-yellow-950',
    substandard: 'bg-orange-50 dark:bg-orange-950',
    doubtful: 'bg-red-50 dark:bg-red-950',
    loss: 'bg-red-100 font-medium dark:bg-red-900',
};
</script>

<template>
    <Head title="Portfolio at Risk" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-xl font-semibold">Portfolio at Risk (PAR) — {{ report.as_of }}</h1>
                <ExportButtons report="par" />
            </div>

            <!-- Headline numbers -->
            <div class="grid gap-4 sm:grid-cols-4">
                <div class="rounded-xl border p-4">
                    <div class="text-sm text-muted-foreground">Gross portfolio</div>
                    <div class="mt-1 font-mono text-xl font-semibold">{{ ugx(report.totals.gross_portfolio) }}</div>
                </div>
                <div class="rounded-xl border p-4">
                    <div class="text-sm text-muted-foreground">Portfolio at risk</div>
                    <div class="mt-1 font-mono text-xl font-semibold text-red-600">{{ ugx(report.totals.portfolio_at_risk) }}</div>
                </div>
                <div class="rounded-xl border p-4">
                    <div class="text-sm text-muted-foreground">PAR ratio</div>
                    <div class="mt-1 text-xl font-semibold" :class="Number(report.totals.par_ratio) > 5 ? 'text-red-600' : 'text-green-600'">
                        {{ report.totals.par_ratio }}%
                    </div>
                </div>
                <div class="rounded-xl border p-4">
                    <div class="text-sm text-muted-foreground">Total provisions</div>
                    <div class="mt-1 font-mono text-xl font-semibold">{{ ugx(report.totals.total_provision) }}</div>
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">Classification</th>
                            <th class="px-4 py-3 font-medium">Arrears</th>
                            <th class="px-4 py-3 text-right font-medium">Provision rate</th>
                            <th class="px-4 py-3 text-right font-medium">Loans</th>
                            <th class="px-4 py-3 text-right font-medium">Outstanding (UGX)</th>
                            <th class="px-4 py-3 text-right font-medium">Provision (UGX)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(bucket, i) in report.buckets" :key="bucket.classification" class="border-b last:border-0" :class="rowClasses[bucket.classification]">
                            <td class="px-4 py-3 font-medium capitalize">{{ bucket.classification }}</td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ ['Current', '1–30 days', '31–60 days', '61–90 days', '> 90 days'][i] }}
                            </td>
                            <td class="px-4 py-3 text-right">{{ bucket.provision_rate }}</td>
                            <td class="px-4 py-3 text-right">{{ bucket.loans }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ ugx(bucket.outstanding) }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ ugx(bucket.provision) }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t bg-muted/30 font-semibold">
                            <td colspan="3" class="px-4 py-3">Total</td>
                            <td class="px-4 py-3 text-right">{{ report.buckets.reduce((s, b) => s + b.loans, 0) }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ ugx(report.totals.gross_portfolio) }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ ugx(report.totals.total_provision) }}</td>
                        </tr>
                        <tr class="font-medium">
                            <td colspan="5" class="px-4 py-2 text-right">Net portfolio (after provisions)</td>
                            <td class="px-4 py-2 text-right font-mono">{{ ugx(report.totals.net_portfolio) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
