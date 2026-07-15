<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Row {
    id: number;
    code: string;
    name: string;
    type: string;
    debit: string;
    credit: string;
}

const props = defineProps<{
    rows: Row[];
    totals: { debit: string; credit: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Trial Balance', href: '/gl/trial-balance' }];

const balanced = computed(() => Number(props.totals.debit) === Number(props.totals.credit));

const ugx = (value: string) => (Number(value) === 0 ? '' : new Intl.NumberFormat('en-UG', { minimumFractionDigits: 2 }).format(Number(value)));
const ugxAlways = (value: string) => new Intl.NumberFormat('en-UG', { minimumFractionDigits: 2 }).format(Number(value));
</script>

<template>
    <Head title="Trial Balance" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold">Trial Balance</h1>
                <span
                    class="rounded-full px-3 py-1 text-sm font-medium"
                    :class="balanced
                        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                        : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'"
                >
                    {{ balanced ? '✓ Books balance' : '✗ OUT OF BALANCE' }}
                </span>
            </div>

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">Code</th>
                            <th class="px-4 py-3 font-medium">Account</th>
                            <th class="px-4 py-3 font-medium">Type</th>
                            <th class="px-4 py-3 text-right font-medium">Debit (UGX)</th>
                            <th class="px-4 py-3 text-right font-medium">Credit (UGX)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows" :key="row.id" class="border-b last:border-0 hover:bg-muted/50">
                            <td class="px-4 py-3 font-mono">{{ row.code }}</td>
                            <td class="px-4 py-3">
                                <Link :href="`/gl/accounts/${row.id}/ledger`" class="hover:underline">{{ row.name }}</Link>
                            </td>
                            <td class="px-4 py-3 capitalize">{{ row.type }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ ugx(row.debit) }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ ugx(row.credit) }}</td>
                        </tr>
                        <tr v-if="rows.length === 0">
                            <td colspan="5" class="px-4 py-10 text-center text-muted-foreground">No postings yet.</td>
                        </tr>
                    </tbody>
                    <tfoot v-if="rows.length > 0">
                        <tr class="border-t bg-muted/30 font-semibold">
                            <td colspan="3" class="px-4 py-3 text-right">Totals</td>
                            <td class="px-4 py-3 text-right font-mono">{{ ugxAlways(totals.debit) }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ ugxAlways(totals.credit) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
