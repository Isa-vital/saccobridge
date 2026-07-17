<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessages from '@/components/FlashMessages.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

interface LoanRow {
    id: number;
    loan_no: string;
    member: string;
    product: string;
    applied_amount: string;
    approved_amount: string | null;
    principal_outstanding: string;
    status: string;
    classification: string;
    days_in_arrears: number;
}

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
}

const props = defineProps<{
    loans: Paginated<LoanRow>;
    stats: { portfolio: string; pending: number; approved: number; in_arrears: number };
    filters: { search?: string; status?: string };
    can: { create: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Loans', href: '/loans' }];

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');

let debounce: ReturnType<typeof setTimeout>;
watch([search, status], () => {
    clearTimeout(debounce);
    debounce = setTimeout(
        () => router.get('/loans', { search: search.value || undefined, status: status.value || undefined }, { preserveState: true, replace: true }),
        300,
    );
});

const ugx = (value: string | null) => (value === null ? '—' : new Intl.NumberFormat('en-UG').format(Number(value)));

const statusClasses: Record<string, string> = {
    submitted: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    under_review: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    approved: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    active: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    rejected: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    closed: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
    written_off: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
};

const classificationClasses: Record<string, string> = {
    performing: 'text-green-600',
    watch: 'text-yellow-600',
    substandard: 'text-orange-600',
    doubtful: 'text-red-600',
    loss: 'font-semibold text-red-700',
};
</script>

<template>
    <Head title="Loans" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <FlashMessages />

            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-xl font-semibold">Loan Portfolio</h1>
                <Link v-if="can.create" href="/loans/create">
                    <Button>New Application</Button>
                </Link>
            </div>

            <!-- Stats -->
            <div class="grid gap-4 sm:grid-cols-4">
                <div class="rounded-xl border p-4">
                    <div class="text-sm text-muted-foreground">Outstanding portfolio</div>
                    <div class="mt-1 font-mono text-xl font-semibold">{{ ugx(stats.portfolio) }}</div>
                </div>
                <div class="rounded-xl border p-4">
                    <div class="text-sm text-muted-foreground">Awaiting approval</div>
                    <div class="mt-1 text-xl font-semibold">{{ stats.pending }}</div>
                </div>
                <div class="rounded-xl border p-4">
                    <div class="text-sm text-muted-foreground">Approved, undisbursed</div>
                    <div class="mt-1 text-xl font-semibold">{{ stats.approved }}</div>
                </div>
                <div class="rounded-xl border p-4">
                    <div class="text-sm text-muted-foreground">Loans in arrears</div>
                    <div class="mt-1 text-xl font-semibold" :class="stats.in_arrears > 0 ? 'text-red-600' : ''">{{ stats.in_arrears }}</div>
                </div>
            </div>

            <div class="flex flex-wrap gap-3">
                <Input v-model="search" placeholder="Search loan no, member…" class="max-w-sm" />
                <select v-model="status" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm">
                    <option value="">All statuses</option>
                    <option value="submitted">Submitted</option>
                    <option value="approved">Approved</option>
                    <option value="active">Active</option>
                    <option value="rejected">Rejected</option>
                    <option value="closed">Closed</option>
                    <option value="written_off">Written off</option>
                </select>
            </div>

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">Loan No</th>
                            <th class="px-4 py-3 font-medium">Member</th>
                            <th class="px-4 py-3 font-medium">Product</th>
                            <th class="px-4 py-3 text-right font-medium">Amount</th>
                            <th class="px-4 py-3 text-right font-medium">Outstanding</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Classification</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="loan in loans.data"
                            :key="loan.id"
                            class="cursor-pointer border-b transition-colors last:border-0 hover:bg-muted/50"
                            @click="router.visit(`/loans/${loan.id}`)"
                        >
                            <td class="px-4 py-3 font-mono">{{ loan.loan_no }}</td>
                            <td class="px-4 py-3">{{ loan.member }}</td>
                            <td class="px-4 py-3 font-mono">{{ loan.product }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ ugx(loan.approved_amount ?? loan.applied_amount) }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ ugx(loan.principal_outstanding) }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="statusClasses[loan.status]">
                                    {{ loan.status.replace(/_/g, ' ') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 capitalize" :class="classificationClasses[loan.classification]">
                                {{ loan.status === 'active' ? loan.classification : '—' }}
                                <span v-if="loan.days_in_arrears > 0" class="text-xs">({{ loan.days_in_arrears }}d)</span>
                            </td>
                        </tr>
                        <tr v-if="loans.data.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-muted-foreground">No loans found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap gap-1" v-if="loans.links.length > 3">
                <template v-for="(link, i) in loans.links" :key="i">
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
