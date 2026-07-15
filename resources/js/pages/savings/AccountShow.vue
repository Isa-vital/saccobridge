<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessages from '@/components/FlashMessages.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface TxnRow {
    id: number;
    reference: string;
    value_date: string;
    type: string;
    amount: string;
    balance_after: string;
    status: string;
    memo: string | null;
    performed_by: string | null;
}

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
}

const props = defineProps<{
    account: {
        id: number;
        account_no: string;
        member: string;
        member_id: number;
        product: string;
        balance: string;
        blocked_amount: string;
        available: string;
        status: string;
        opened_at: string;
    };
    transactions: Paginated<TxnRow>;
    filters: { from: string; to: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Savings Accounts', href: '/savings/accounts' },
    { title: props.account.account_no, href: `/savings/accounts/${props.account.id}` },
];

const from = ref(props.filters.from);
const to = ref(props.filters.to);

const applyRange = () => {
    router.get(`/savings/accounts/${props.account.id}`, { from: from.value, to: to.value }, { preserveState: true });
};

const ugx = (value: string) => new Intl.NumberFormat('en-UG').format(Number(value));

const credit = (type: string) => ['deposit', 'transfer_in', 'interest', 'dividend_credit', 'loan_disbursement'].includes(type);
</script>

<template>
    <Head :title="account.account_no" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <FlashMessages />

            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">{{ account.account_no }}</h1>
                    <p class="text-sm text-muted-foreground">{{ account.member }} · {{ account.product }} · opened {{ account.opened_at }}</p>
                </div>
                <div class="flex gap-6 text-right">
                    <div>
                        <div class="text-xs text-muted-foreground">Balance</div>
                        <div class="font-mono text-lg font-semibold">{{ ugx(account.balance) }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-muted-foreground">Blocked</div>
                        <div class="font-mono text-lg">{{ ugx(account.blocked_amount) }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-muted-foreground">Available</div>
                        <div class="font-mono text-lg text-green-600">{{ ugx(account.available) }}</div>
                    </div>
                </div>
            </div>

            <!-- Statement range -->
            <div class="flex flex-wrap items-end gap-3">
                <div class="grid gap-1">
                    <label class="text-xs text-muted-foreground">From</label>
                    <Input v-model="from" type="date" />
                </div>
                <div class="grid gap-1">
                    <label class="text-xs text-muted-foreground">To</label>
                    <Input v-model="to" type="date" />
                </div>
                <Button variant="outline" @click="applyRange">Apply</Button>
            </div>

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">Receipt</th>
                            <th class="px-4 py-3 font-medium">Date</th>
                            <th class="px-4 py-3 font-medium">Type</th>
                            <th class="px-4 py-3 text-right font-medium">In</th>
                            <th class="px-4 py-3 text-right font-medium">Out</th>
                            <th class="px-4 py-3 text-right font-medium">Balance</th>
                            <th class="px-4 py-3 font-medium">By</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="txn in transactions.data" :key="txn.id" class="border-b last:border-0 hover:bg-muted/50">
                            <td class="px-4 py-3 font-mono">{{ txn.reference }}</td>
                            <td class="px-4 py-3">{{ txn.value_date }}</td>
                            <td class="px-4 py-3 capitalize">
                                {{ txn.type.replace(/_/g, ' ') }}
                                <span v-if="txn.memo" class="text-muted-foreground"> — {{ txn.memo }}</span>
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-green-600">{{ credit(txn.type) ? ugx(txn.amount) : '' }}</td>
                            <td class="px-4 py-3 text-right font-mono text-red-600">{{ !credit(txn.type) ? ugx(txn.amount) : '' }}</td>
                            <td class="px-4 py-3 text-right font-mono font-medium">{{ ugx(txn.balance_after) }}</td>
                            <td class="px-4 py-3">{{ txn.performed_by ?? 'system' }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                    :class="{
                                        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': txn.status === 'completed',
                                        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': txn.status === 'pending_approval',
                                        'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': txn.status === 'rejected',
                                    }"
                                >
                                    {{ txn.status.replace(/_/g, ' ') }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="transactions.data.length === 0">
                            <td colspan="8" class="px-4 py-10 text-center text-muted-foreground">No transactions in this period.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap gap-1" v-if="transactions.links.length > 3">
                <template v-for="(link, i) in transactions.links" :key="i">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded-md px-3 py-1.5 text-sm"
                        :class="link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
                        v-html="link.label"
                    />
                    <span v-else class="px-3 py-1.5 text-sm text-muted-foreground" v-html="link.label" />
                </template>
            </div>
        </div>
    </AppLayout>
</template>
