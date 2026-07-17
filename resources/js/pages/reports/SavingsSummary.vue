<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import ExportButtons from '@/components/ExportButtons.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

interface ProductRow {
    code: string;
    name: string;
    accounts: number;
    balance: string;
    blocked: string;
}

defineProps<{
    report: {
        as_of: string;
        products: ProductRow[];
        totals: { accounts: number; balance: string; blocked: string };
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Reports', href: '/reports' },
    { title: 'Savings Summary', href: '/reports/savings-summary' },
];

const ugx = (value: string) => new Intl.NumberFormat('en-UG', { minimumFractionDigits: 2 }).format(Number(value));
</script>

<template>
    <Head title="Savings Summary" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-xl font-semibold">Savings Summary — {{ report.as_of }}</h1>
                <ExportButtons report="savings-summary" />
            </div>

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">Code</th>
                            <th class="px-4 py-3 font-medium">Product</th>
                            <th class="px-4 py-3 text-right font-medium">Accounts</th>
                            <th class="px-4 py-3 text-right font-medium">Balance (UGX)</th>
                            <th class="px-4 py-3 text-right font-medium">Blocked (UGX)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="product in report.products" :key="product.code" class="border-b last:border-0 hover:bg-muted/50">
                            <td class="px-4 py-3 font-mono">{{ product.code }}</td>
                            <td class="px-4 py-3">{{ product.name }}</td>
                            <td class="px-4 py-3 text-right">{{ product.accounts }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ ugx(product.balance) }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ ugx(product.blocked) }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t bg-muted/30 font-semibold">
                            <td colspan="2" class="px-4 py-3">Total</td>
                            <td class="px-4 py-3 text-right">{{ report.totals.accounts }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ ugx(report.totals.balance) }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ ugx(report.totals.blocked) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
