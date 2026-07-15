<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

interface LedgerRow {
    id: number;
    date: string;
    reference: string;
    description: string;
    memo: string | null;
    debit: string;
    credit: string;
    balance: string;
    entry_status: string;
}

const props = defineProps<{
    account: { id: number; code: string; name: string; type: string };
    ledger: LedgerRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Chart of Accounts', href: '/gl/accounts' },
    { title: `${props.account.code} ${props.account.name}`, href: `/gl/accounts/${props.account.id}/ledger` },
];

const ugx = (value: string) => (Number(value) === 0 ? '' : new Intl.NumberFormat('en-UG', { minimumFractionDigits: 2 }).format(Number(value)));
const ugxAlways = (value: string) => new Intl.NumberFormat('en-UG', { minimumFractionDigits: 2 }).format(Number(value));
</script>

<template>
    <Head :title="`Ledger — ${account.code}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <h1 class="text-xl font-semibold">
                Ledger: {{ account.code }} — {{ account.name }}
                <span class="ml-2 text-sm font-normal capitalize text-muted-foreground">({{ account.type }})</span>
            </h1>

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">Date</th>
                            <th class="px-4 py-3 font-medium">Reference</th>
                            <th class="px-4 py-3 font-medium">Description</th>
                            <th class="px-4 py-3 text-right font-medium">Debit</th>
                            <th class="px-4 py-3 text-right font-medium">Credit</th>
                            <th class="px-4 py-3 text-right font-medium">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in ledger" :key="row.id" class="border-b last:border-0 hover:bg-muted/50">
                            <td class="px-4 py-3">{{ row.date }}</td>
                            <td class="px-4 py-3 font-mono">
                                <span :class="{ 'line-through opacity-60': row.entry_status === 'reversed' }">{{ row.reference }}</span>
                            </td>
                            <td class="px-4 py-3">
                                {{ row.description }}
                                <span v-if="row.memo" class="text-muted-foreground"> — {{ row.memo }}</span>
                            </td>
                            <td class="px-4 py-3 text-right font-mono">{{ ugx(row.debit) }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ ugx(row.credit) }}</td>
                            <td class="px-4 py-3 text-right font-mono font-medium">{{ ugxAlways(row.balance) }}</td>
                        </tr>
                        <tr v-if="ledger.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">No transactions on this account.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
