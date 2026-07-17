<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import ExportButtons from '@/components/ExportButtons.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Line {
    code: string;
    name: string;
    balance: string;
}

const props = defineProps<{
    report: {
        from: string;
        to: string;
        income: Line[];
        expenses: Line[];
        totals: { income: string; expenses: string; surplus: string };
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Reports', href: '/reports' },
    { title: 'Income Statement', href: '/reports/income-statement' },
];

const from = ref(props.report.from);
const to = ref(props.report.to);
const apply = () => router.get('/reports/income-statement', { from: from.value, to: to.value }, { preserveState: true });

const ugx = (value: string) => new Intl.NumberFormat('en-UG', { minimumFractionDigits: 2 }).format(Number(value));
</script>

<template>
    <Head title="Income Statement" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-xl font-semibold">Income Statement</h1>
                <div class="flex items-end gap-2">
                    <div class="grid gap-1"><label class="text-xs text-muted-foreground">From</label><Input v-model="from" type="date" /></div>
                    <div class="grid gap-1"><label class="text-xs text-muted-foreground">To</label><Input v-model="to" type="date" /></div>
                    <Button variant="outline" @click="apply">Apply</Button>
                    <ExportButtons report="income-statement" :query="{ from, to }" />
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-xl border">
                    <div class="border-b bg-muted/50 px-4 py-2 font-medium">Income</div>
                    <table class="w-full text-sm">
                        <tbody>
                            <tr v-for="line in report.income" :key="line.code" class="border-b last:border-0">
                                <td class="px-4 py-2 font-mono">{{ line.code }}</td>
                                <td class="px-4 py-2">{{ line.name }}</td>
                                <td class="px-4 py-2 text-right font-mono">{{ ugx(line.balance) }}</td>
                            </tr>
                            <tr v-if="report.income.length === 0"><td colspan="3" class="px-4 py-6 text-center text-muted-foreground">No income in this period.</td></tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t bg-muted/30 font-semibold">
                                <td colspan="2" class="px-4 py-2">Total Income</td>
                                <td class="px-4 py-2 text-right font-mono">{{ ugx(report.totals.income) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="rounded-xl border">
                    <div class="border-b bg-muted/50 px-4 py-2 font-medium">Expenses</div>
                    <table class="w-full text-sm">
                        <tbody>
                            <tr v-for="line in report.expenses" :key="line.code" class="border-b last:border-0">
                                <td class="px-4 py-2 font-mono">{{ line.code }}</td>
                                <td class="px-4 py-2">{{ line.name }}</td>
                                <td class="px-4 py-2 text-right font-mono">{{ ugx(line.balance) }}</td>
                            </tr>
                            <tr v-if="report.expenses.length === 0"><td colspan="3" class="px-4 py-6 text-center text-muted-foreground">No expenses in this period.</td></tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t bg-muted/30 font-semibold">
                                <td colspan="2" class="px-4 py-2">Total Expenses</td>
                                <td class="px-4 py-2 text-right font-mono">{{ ugx(report.totals.expenses) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="flex items-center justify-between rounded-xl border p-4">
                <span class="font-medium">Net Surplus / (Deficit) for the period</span>
                <span class="font-mono text-xl font-semibold" :class="Number(report.totals.surplus) >= 0 ? 'text-green-600' : 'text-red-600'">
                    {{ ugx(report.totals.surplus) }}
                </span>
            </div>
        </div>
    </AppLayout>
</template>
