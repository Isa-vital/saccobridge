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
        as_of: string;
        assets: Line[];
        liabilities: Line[];
        equity: Line[];
        totals: { assets: string; liabilities: string; equity: string; surplus: string; liabilities_and_equity: string };
        balanced: boolean;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Reports', href: '/reports' },
    { title: 'Balance Sheet', href: '/reports/balance-sheet' },
];

const asOf = ref(props.report.as_of);
const apply = () => router.get('/reports/balance-sheet', { as_of: asOf.value }, { preserveState: true });

const ugx = (value: string) => new Intl.NumberFormat('en-UG', { minimumFractionDigits: 2 }).format(Number(value));
</script>

<template>
    <Head title="Balance Sheet" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <h1 class="text-xl font-semibold">Balance Sheet</h1>
                    <span
                        class="rounded-full px-3 py-1 text-sm font-medium"
                        :class="report.balanced
                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                            : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'"
                    >
                        {{ report.balanced ? '✓ Balanced' : '✗ OUT OF BALANCE' }}
                    </span>
                </div>
                <div class="flex items-end gap-2">
                    <div class="grid gap-1">
                        <label class="text-xs text-muted-foreground">As of</label>
                        <Input v-model="asOf" type="date" />
                    </div>
                    <Button variant="outline" @click="apply">Apply</Button>
                    <ExportButtons report="balance-sheet" :query="{ as_of: asOf }" />
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <!-- Assets -->
                <div class="rounded-xl border">
                    <div class="border-b bg-muted/50 px-4 py-2 font-medium">Assets</div>
                    <table class="w-full text-sm">
                        <tbody>
                            <tr v-for="line in report.assets" :key="line.code" class="border-b last:border-0">
                                <td class="px-4 py-2 font-mono">{{ line.code }}</td>
                                <td class="px-4 py-2">{{ line.name }}</td>
                                <td class="px-4 py-2 text-right font-mono">{{ ugx(line.balance) }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t bg-muted/30 font-semibold">
                                <td colspan="2" class="px-4 py-2">Total Assets</td>
                                <td class="px-4 py-2 text-right font-mono">{{ ugx(report.totals.assets) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Liabilities + equity -->
                <div class="flex flex-col gap-4">
                    <div class="rounded-xl border">
                        <div class="border-b bg-muted/50 px-4 py-2 font-medium">Liabilities</div>
                        <table class="w-full text-sm">
                            <tbody>
                                <tr v-for="line in report.liabilities" :key="line.code" class="border-b last:border-0">
                                    <td class="px-4 py-2 font-mono">{{ line.code }}</td>
                                    <td class="px-4 py-2">{{ line.name }}</td>
                                    <td class="px-4 py-2 text-right font-mono">{{ ugx(line.balance) }}</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="border-t bg-muted/30 font-semibold">
                                    <td colspan="2" class="px-4 py-2">Total Liabilities</td>
                                    <td class="px-4 py-2 text-right font-mono">{{ ugx(report.totals.liabilities) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="rounded-xl border">
                        <div class="border-b bg-muted/50 px-4 py-2 font-medium">Equity</div>
                        <table class="w-full text-sm">
                            <tbody>
                                <tr v-for="line in report.equity" :key="line.code" class="border-b">
                                    <td class="px-4 py-2 font-mono">{{ line.code }}</td>
                                    <td class="px-4 py-2">{{ line.name }}</td>
                                    <td class="px-4 py-2 text-right font-mono">{{ ugx(line.balance) }}</td>
                                </tr>
                                <tr class="border-b">
                                    <td class="px-4 py-2"></td>
                                    <td class="px-4 py-2">Net Surplus (YTD)</td>
                                    <td class="px-4 py-2 text-right font-mono">{{ ugx(report.totals.surplus) }}</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="border-t bg-muted/30 font-semibold">
                                    <td colspan="2" class="px-4 py-2">Total Liabilities &amp; Equity</td>
                                    <td class="px-4 py-2 text-right font-mono">{{ ugx(report.totals.liabilities_and_equity) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
