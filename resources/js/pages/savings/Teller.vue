<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessages from '@/components/FlashMessages.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    session: { id: number; opening_float: string; expected_cash: string; opened_at: string } | null;
    account: {
        id: number;
        account_no: string;
        member: string;
        product: string;
        balance: string;
        available: string;
        status: string;
        withdrawal_fee: string;
    } | null;
    searched: boolean;
    search: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Teller Station', href: '/savings/teller' }];

const accountNo = ref(props.search ?? '');
const findAccount = () => {
    router.get('/savings/teller', { account: accountNo.value }, { preserveState: true });
};

const depositForm = useForm({ account_id: 0, amount: '', memo: '' });
const withdrawForm = useForm({ account_id: 0, amount: '', memo: '' });

const submitDeposit = () => {
    if (!props.account) return;
    depositForm.account_id = props.account.id;
    depositForm.post('/savings/teller/deposit', {
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => depositForm.reset('amount', 'memo'),
    });
};

const submitWithdraw = () => {
    if (!props.account) return;
    withdrawForm.account_id = props.account.id;
    withdrawForm.post('/savings/teller/withdraw', {
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => withdrawForm.reset('amount', 'memo'),
    });
};

const ugx = (value: string) => new Intl.NumberFormat('en-UG').format(Number(value));
</script>

<template>
    <Head title="Teller Station" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <FlashMessages />

            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-xl font-semibold">Teller Station</h1>
                <div v-if="session" class="flex gap-5 rounded-xl border px-4 py-2 text-sm">
                    <span>Session <strong>#{{ session.id }}</strong> · since {{ session.opened_at }}</span>
                    <span>Float: <strong class="font-mono">{{ ugx(session.opening_float) }}</strong></span>
                    <span>Drawer: <strong class="font-mono">{{ ugx(session.expected_cash) }}</strong></span>
                </div>
            </div>

            <div
                v-if="!session"
                class="rounded-xl border border-yellow-300 bg-yellow-50 px-4 py-3 text-sm text-yellow-800 dark:border-yellow-800 dark:bg-yellow-950 dark:text-yellow-200"
            >
                You have no open teller session. A manager must issue you a float (Teller Sessions page) before you can take cash.
            </div>

            <!-- Account lookup -->
            <form class="flex max-w-md gap-2" @submit.prevent="findAccount">
                <Input v-model="accountNo" placeholder="Enter account no, e.g. SAV-00001" class="font-mono" />
                <Button type="submit" variant="outline">Find</Button>
            </form>

            <div v-if="searched && !account" class="text-sm text-destructive">Account not found.</div>

            <template v-if="account">
                <div class="rounded-xl border p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <div class="font-mono text-lg font-semibold">{{ account.account_no }}</div>
                            <div class="text-sm text-muted-foreground">{{ account.member }} · {{ account.product }}</div>
                        </div>
                        <div class="flex gap-6 text-right">
                            <div>
                                <div class="text-xs text-muted-foreground">Balance</div>
                                <div class="font-mono font-semibold">{{ ugx(account.balance) }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">Available</div>
                                <div class="font-mono font-semibold text-green-600">{{ ugx(account.available) }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-muted-foreground">Status</div>
                                <div class="capitalize">{{ account.status }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <!-- Deposit -->
                    <form class="grid gap-3 rounded-xl border p-4" @submit.prevent="submitDeposit">
                        <h2 class="font-medium text-green-700 dark:text-green-400">Deposit</h2>
                        <div class="grid gap-1.5">
                            <Label for="dep_amount">Amount (UGX)</Label>
                            <Input id="dep_amount" v-model="depositForm.amount" type="number" step="0.01" min="0.01" required />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="dep_memo">Memo</Label>
                            <Input id="dep_memo" v-model="depositForm.memo" placeholder="optional" />
                        </div>
                        <Button type="submit" :disabled="!session || depositForm.processing">Take deposit</Button>
                    </form>

                    <!-- Withdrawal -->
                    <form class="grid gap-3 rounded-xl border p-4" @submit.prevent="submitWithdraw">
                        <h2 class="font-medium text-red-700 dark:text-red-400">Withdrawal</h2>
                        <div class="grid gap-1.5">
                            <Label for="wd_amount">Amount (UGX)</Label>
                            <Input id="wd_amount" v-model="withdrawForm.amount" type="number" step="0.01" min="0.01" required />
                            <p v-if="Number(account.withdrawal_fee) > 0" class="text-xs text-muted-foreground">
                                Withdrawal fee: {{ ugx(account.withdrawal_fee) }}
                            </p>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="wd_memo">Memo</Label>
                            <Input id="wd_memo" v-model="withdrawForm.memo" placeholder="optional" />
                        </div>
                        <Button type="submit" variant="destructive" :disabled="!session || withdrawForm.processing">Pay out</Button>
                    </form>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
