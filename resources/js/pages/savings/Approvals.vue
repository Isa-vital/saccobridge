<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessages from '@/components/FlashMessages.vue';
import { Button } from '@/components/ui/button';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface PendingRow {
    id: number;
    reference: string;
    account_no: string;
    member: string;
    amount: string;
    memo: string | null;
    performed_by: string | null;
    performed_by_id: number | null;
    created_at: string;
}

defineProps<{ pending: PendingRow[] }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Withdrawal Approvals', href: '/savings/approvals' }];

const page = usePage();
const myId = computed(() => (page.props as any).auth?.user?.id);

const approve = (row: PendingRow) => {
    if (confirm(`Approve withdrawal ${row.reference} of ${row.amount} for ${row.member}?`)) {
        router.post(`/savings/approvals/${row.id}/approve`, {}, { preserveScroll: true });
    }
};

const reject = (row: PendingRow) => {
    if (confirm(`Reject withdrawal ${row.reference}?`)) {
        router.post(`/savings/approvals/${row.id}/reject`, {}, { preserveScroll: true });
    }
};

const ugx = (value: string) => new Intl.NumberFormat('en-UG').format(Number(value));
</script>

<template>
    <Head title="Withdrawal Approvals" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <FlashMessages />

            <h1 class="text-xl font-semibold">Withdrawal Approvals</h1>
            <p class="text-sm text-muted-foreground">
                Withdrawals above the configured threshold wait here until a manager approves them (maker-checker).
            </p>

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">Receipt</th>
                            <th class="px-4 py-3 font-medium">Account</th>
                            <th class="px-4 py-3 font-medium">Member</th>
                            <th class="px-4 py-3 text-right font-medium">Amount (UGX)</th>
                            <th class="px-4 py-3 font-medium">Teller</th>
                            <th class="px-4 py-3 font-medium">Requested</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in pending" :key="row.id" class="border-b last:border-0 hover:bg-muted/50">
                            <td class="px-4 py-3 font-mono">{{ row.reference }}</td>
                            <td class="px-4 py-3 font-mono">{{ row.account_no }}</td>
                            <td class="px-4 py-3">
                                {{ row.member }}
                                <span v-if="row.memo" class="text-muted-foreground"> — {{ row.memo }}</span>
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-semibold">{{ ugx(row.amount) }}</td>
                            <td class="px-4 py-3">{{ row.performed_by ?? '—' }}</td>
                            <td class="px-4 py-3">{{ row.created_at }}</td>
                            <td class="px-4 py-3 text-right">
                                <span v-if="row.performed_by_id === myId" class="text-xs text-muted-foreground">
                                    own transaction — another manager must decide
                                </span>
                                <template v-else>
                                    <Button size="sm" class="mr-2" @click="approve(row)">Approve</Button>
                                    <Button size="sm" variant="destructive" @click="reject(row)">Reject</Button>
                                </template>
                            </td>
                        </tr>
                        <tr v-if="pending.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-muted-foreground">Nothing awaiting approval. 🎉</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
