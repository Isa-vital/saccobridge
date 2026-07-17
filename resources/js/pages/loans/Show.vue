<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessages from '@/components/FlashMessages.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface ScheduleRow {
    installment_no: number;
    due_date: string;
    principal_due: string;
    interest_due: string;
    penalties_due: string;
    total_paid: string;
    balance_after: string;
    is_settled: boolean;
}

const props = defineProps<{
    loan: {
        id: number;
        loan_no: string;
        member: string;
        product: string;
        interest_method: string;
        interest_rate: string;
        applied_amount: string;
        applied_term_months: number;
        approved_amount: string | null;
        approved_term_months: number | null;
        purpose: string | null;
        status: string;
        classification: string;
        days_in_arrears: number;
        provision_amount: string;
        principal_outstanding: string;
        interest_outstanding: string;
        fees_outstanding: string;
        penalties_outstanding: string;
        total_outstanding: string;
        disbursed_at: string | null;
        created_by: number | null;
        creator: string | null;
        approver: string | null;
        rejection_reason: string | null;
        schedules: ScheduleRow[];
        repayments: { reference: string; value_date: string; amount: string; principal: string; interest: string; penalties: string; source: string; by: string | null }[];
        guarantors: { member: string; amount: string; status: string }[];
        collateral: { id: number; description: string; estimated_value: string; status: string }[];
    };
    costOfCredit: { total_interest: string; total_fees: string; total_cost: string; total_repayable: string; net_disbursed: string } | null;
    payoffQuote: { principal: string; interest_due: string; penalties_due: string; waived_future_interest: string; total: string; as_of: string } | null;
    can: { approve: boolean; disburse: boolean; repay: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Loans', href: '/loans' },
    { title: props.loan.loan_no, href: `/loans/${props.loan.id}` },
];

// Approve form
const showApprove = ref(false);
const approvedAmount = ref(props.loan.applied_amount);
const approvedTerm = ref(props.loan.applied_term_months);
const rejectReason = ref('');
const showReject = ref(false);

const approve = () => {
    router.post(`/loans/${props.loan.id}/approve`, { approved_amount: approvedAmount.value, approved_term_months: approvedTerm.value }, { preserveScroll: true });
};
const reject = () => {
    router.post(`/loans/${props.loan.id}/reject`, { reason: rejectReason.value }, { preserveScroll: true });
};

// Disburse
const disburseMethod = ref<'cash' | 'savings'>('cash');
const disburse = () => {
    if (confirm(`Disburse ${props.loan.loan_no} via ${disburseMethod.value}? The amortization schedule will be generated.`)) {
        router.post(`/loans/${props.loan.id}/disburse`, { method: disburseMethod.value }, { preserveScroll: true });
    }
};

// Repay
const repayAmount = ref('');
const repaySource = ref<'cash' | 'savings'>('cash');
const repay = () => {
    router.post(`/loans/${props.loan.id}/repay`, { amount: repayAmount.value, source: repaySource.value }, { preserveScroll: true, onSuccess: () => (repayAmount.value = '') });
};

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
</script>

<template>
    <Head :title="loan.loan_no" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <FlashMessages />

            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="font-mono text-xl font-semibold">{{ loan.loan_no }}</h1>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium capitalize" :class="statusClasses[loan.status]">
                            {{ loan.status.replace(/_/g, ' ') }}
                        </span>
                        <span v-if="loan.status === 'active' && loan.days_in_arrears > 0" class="text-sm font-medium capitalize text-red-600">
                            {{ loan.classification }} · {{ loan.days_in_arrears }} days in arrears
                        </span>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{ loan.member }} · {{ loan.product }} ({{ Number(loan.interest_rate) }}% {{ loan.interest_method.replace('_', ' ') }})
                        <span v-if="loan.purpose"> · {{ loan.purpose }}</span>
                    </p>
                    <p v-if="loan.rejection_reason" class="text-sm text-red-600">Rejected: {{ loan.rejection_reason }}</p>
                </div>
                <div class="text-right">
                    <div class="text-xs text-muted-foreground">Total outstanding</div>
                    <div class="font-mono text-2xl font-semibold">{{ ugx(loan.total_outstanding) }}</div>
                </div>
            </div>

            <!-- Outstanding breakdown -->
            <div v-if="loan.status === 'active'" class="grid gap-4 sm:grid-cols-4">
                <div class="rounded-xl border p-3 text-sm"><span class="text-muted-foreground">Principal</span><div class="font-mono font-medium">{{ ugx(loan.principal_outstanding) }}</div></div>
                <div class="rounded-xl border p-3 text-sm"><span class="text-muted-foreground">Interest</span><div class="font-mono font-medium">{{ ugx(loan.interest_outstanding) }}</div></div>
                <div class="rounded-xl border p-3 text-sm"><span class="text-muted-foreground">Fees</span><div class="font-mono font-medium">{{ ugx(loan.fees_outstanding) }}</div></div>
                <div class="rounded-xl border p-3 text-sm"><span class="text-muted-foreground">Penalties</span><div class="font-mono font-medium">{{ ugx(loan.penalties_outstanding) }}</div></div>
            </div>

            <!-- Cost of credit (pre-disbursement disclosure) -->
            <div v-if="costOfCredit" class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm dark:border-blue-900 dark:bg-blue-950">
                <h2 class="mb-2 font-medium">Total Cost of Credit</h2>
                <div class="grid gap-x-8 gap-y-1 sm:grid-cols-2 md:grid-cols-3">
                    <div class="flex justify-between"><span>Total interest</span><span class="font-mono">{{ ugx(costOfCredit.total_interest) }}</span></div>
                    <div class="flex justify-between"><span>Total fees</span><span class="font-mono">{{ ugx(costOfCredit.total_fees) }}</span></div>
                    <div class="flex justify-between"><span>Total repayable</span><span class="font-mono">{{ ugx(costOfCredit.total_repayable) }}</span></div>
                    <div class="flex justify-between"><span>Net disbursed</span><span class="font-mono">{{ ugx(costOfCredit.net_disbursed) }}</span></div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex flex-wrap gap-3">
                <template v-if="['submitted', 'under_review'].includes(loan.status)">
                    <template v-if="can.approve">
                        <Button @click="showApprove = !showApprove">Approve…</Button>
                        <Button variant="destructive" @click="showReject = !showReject">Reject…</Button>
                    </template>
                    <span v-else class="self-center text-sm text-muted-foreground">
                        Awaiting approval by another officer (maker-checker)
                    </span>
                </template>

                <template v-if="loan.status === 'approved' && can.disburse">
                    <select v-model="disburseMethod" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm">
                        <option value="cash">Disburse cash (teller)</option>
                        <option value="savings">Credit to savings</option>
                    </select>
                    <Button @click="disburse">Disburse</Button>
                </template>
            </div>

            <div v-if="showApprove" class="flex flex-wrap items-end gap-3 rounded-xl border p-4">
                <div class="grid gap-1.5">
                    <Label>Approved amount</Label>
                    <Input v-model="approvedAmount" type="number" step="0.01" class="w-44" />
                </div>
                <div class="grid gap-1.5">
                    <Label>Approved term (months)</Label>
                    <Input v-model.number="approvedTerm" type="number" min="1" class="w-32" />
                </div>
                <Button @click="approve">Confirm approval</Button>
            </div>

            <div v-if="showReject" class="flex flex-wrap items-end gap-3 rounded-xl border p-4">
                <div class="grid flex-1 gap-1.5">
                    <Label>Rejection reason</Label>
                    <Input v-model="rejectReason" />
                </div>
                <Button variant="destructive" :disabled="rejectReason.length < 3" @click="reject">Confirm rejection</Button>
            </div>

            <!-- Repayment + payoff -->
            <div v-if="loan.status === 'active'" class="grid gap-4 md:grid-cols-2">
                <div v-if="can.repay" class="flex flex-wrap items-end gap-3 rounded-xl border p-4">
                    <div class="grid gap-1.5">
                        <Label>Repayment amount</Label>
                        <Input v-model="repayAmount" type="number" step="0.01" min="0.01" class="w-44" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Source</Label>
                        <select v-model="repaySource" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm">
                            <option value="cash">Cash (teller)</option>
                            <option value="savings">From savings</option>
                        </select>
                    </div>
                    <Button :disabled="!repayAmount" @click="repay">Post repayment</Button>
                </div>

                <div v-if="payoffQuote" class="rounded-xl border p-4 text-sm">
                    <h2 class="mb-2 font-medium">Early Settlement Quote ({{ payoffQuote.as_of }})</h2>
                    <div class="grid gap-1">
                        <div class="flex justify-between"><span>Principal</span><span class="font-mono">{{ ugx(payoffQuote.principal) }}</span></div>
                        <div class="flex justify-between"><span>Interest due</span><span class="font-mono">{{ ugx(payoffQuote.interest_due) }}</span></div>
                        <div class="flex justify-between"><span>Penalties due</span><span class="font-mono">{{ ugx(payoffQuote.penalties_due) }}</span></div>
                        <div class="flex justify-between text-green-700"><span>Future interest waived</span><span class="font-mono">−{{ ugx(payoffQuote.waived_future_interest) }}</span></div>
                        <div class="flex justify-between border-t pt-1 font-semibold"><span>Payoff total</span><span class="font-mono">{{ ugx(payoffQuote.total) }}</span></div>
                    </div>
                </div>
            </div>

            <!-- Guarantors & collateral -->
            <div class="grid gap-4 md:grid-cols-2" v-if="loan.guarantors.length || loan.collateral.length">
                <div v-if="loan.guarantors.length" class="rounded-xl border p-4">
                    <h2 class="mb-2 font-medium">Guarantors</h2>
                    <div v-for="(guarantor, i) in loan.guarantors" :key="i" class="flex justify-between border-b py-1.5 text-sm last:border-0">
                        <span>{{ guarantor.member }}</span>
                        <span class="font-mono">{{ ugx(guarantor.amount) }} <span class="text-xs text-muted-foreground">({{ guarantor.status }})</span></span>
                    </div>
                </div>
                <div v-if="loan.collateral.length" class="rounded-xl border p-4">
                    <h2 class="mb-2 font-medium">Collateral</h2>
                    <div v-for="item in loan.collateral" :key="item.id" class="flex justify-between border-b py-1.5 text-sm last:border-0">
                        <span>{{ item.description }}</span>
                        <span class="font-mono">{{ ugx(item.estimated_value) }} <span class="text-xs text-muted-foreground">({{ item.status }})</span></span>
                    </div>
                </div>
            </div>

            <!-- Amortization schedule -->
            <div v-if="loan.schedules.length" class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">#</th>
                            <th class="px-4 py-3 font-medium">Due date</th>
                            <th class="px-4 py-3 text-right font-medium">Principal</th>
                            <th class="px-4 py-3 text-right font-medium">Interest</th>
                            <th class="px-4 py-3 text-right font-medium">Penalties</th>
                            <th class="px-4 py-3 text-right font-medium">Paid</th>
                            <th class="px-4 py-3 text-right font-medium">Balance</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in loan.schedules" :key="row.installment_no" class="border-b last:border-0" :class="row.is_settled ? 'opacity-60' : ''">
                            <td class="px-4 py-2">{{ row.installment_no }}</td>
                            <td class="px-4 py-2">{{ row.due_date }}</td>
                            <td class="px-4 py-2 text-right font-mono">{{ ugx(row.principal_due) }}</td>
                            <td class="px-4 py-2 text-right font-mono">{{ ugx(row.interest_due) }}</td>
                            <td class="px-4 py-2 text-right font-mono">{{ Number(row.penalties_due) > 0 ? ugx(row.penalties_due) : '' }}</td>
                            <td class="px-4 py-2 text-right font-mono">{{ ugx(row.total_paid) }}</td>
                            <td class="px-4 py-2 text-right font-mono">{{ ugx(row.balance_after) }}</td>
                            <td class="px-4 py-2">
                                <span v-if="row.is_settled" class="text-green-600">✓ settled</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Repayment history -->
            <div v-if="loan.repayments.length" class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <div class="border-b px-4 py-3 font-medium">Repayments</div>
                <table class="w-full text-sm">
                    <tbody>
                        <tr v-for="repayment in loan.repayments" :key="repayment.reference" class="border-b last:border-0">
                            <td class="px-4 py-2 font-mono">{{ repayment.reference }}</td>
                            <td class="px-4 py-2">{{ repayment.value_date }}</td>
                            <td class="px-4 py-2 text-right font-mono font-medium">{{ ugx(repayment.amount) }}</td>
                            <td class="px-4 py-2 text-right text-xs text-muted-foreground">
                                P {{ ugx(repayment.principal) }} · I {{ ugx(repayment.interest) }}
                                <span v-if="Number(repayment.penalties) > 0"> · Pen {{ ugx(repayment.penalties) }}</span>
                            </td>
                            <td class="px-4 py-2 capitalize">{{ repayment.source }}</td>
                            <td class="px-4 py-2">{{ repayment.by ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
