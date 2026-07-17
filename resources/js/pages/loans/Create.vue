<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessages from '@/components/FlashMessages.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';

interface Product {
    id: number;
    code: string;
    name: string;
    interest_rate: string;
    interest_method: string;
    min_term_months: number;
    max_term_months: number;
    min_amount: string;
    max_amount: string | null;
    required_guarantors: number;
    savings_multiple: string | null;
}

interface CostPreview {
    principal: string;
    total_interest: string;
    total_fees: string;
    total_cost: string;
    total_repayable: string;
    net_disbursed: string;
    interest_method: string;
    annual_rate: string;
}

const props = defineProps<{
    products: Product[];
    costPreview: CostPreview | null;
    previewInput: { loan_product_id?: string; amount?: string; term_months?: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Loans', href: '/loans' },
    { title: 'New Application', href: '/loans/create' },
];

const form = useForm({
    member_id: '' as string | number,
    loan_product_id: props.previewInput.loan_product_id ? Number(props.previewInput.loan_product_id) : (null as number | null),
    amount: props.previewInput.amount ?? '',
    term_months: props.previewInput.term_months ? Number(props.previewInput.term_months) : 12,
    purpose: '',
    guarantors: [] as { member_id: string; savings_account_id: string; guaranteed_amount: string }[],
    collateral: [] as { description: string; estimated_value: string }[],
});

const previewCost = () => {
    if (!form.loan_product_id || !form.amount || !form.term_months) return;
    router.get(
        '/loans/create',
        { loan_product_id: form.loan_product_id, amount: form.amount, term_months: form.term_months },
        { preserveState: true, only: ['costPreview', 'previewInput'] },
    );
};

const submit = () => form.post('/loans');

const ugx = (value: string) => new Intl.NumberFormat('en-UG').format(Number(value));
</script>

<template>
    <Head title="New Loan Application" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <form class="flex flex-col gap-5 p-4" @submit.prevent="submit">
            <FlashMessages />
            <h1 class="text-xl font-semibold">New Loan Application</h1>

            <div class="grid gap-4 md:grid-cols-4">
                <div class="grid gap-1.5">
                    <Label for="member_id">Member ID</Label>
                    <Input id="member_id" v-model="form.member_id" placeholder="numeric member ID" required />
                    <p v-if="form.errors.member_id" class="text-xs text-destructive">{{ form.errors.member_id }}</p>
                </div>
                <div class="grid gap-1.5">
                    <Label for="product">Product</Label>
                    <select id="product" v-model="form.loan_product_id" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm" required @change="previewCost">
                        <option :value="null" disabled>Select…</option>
                        <option v-for="product in products" :key="product.id" :value="product.id">
                            {{ product.code }} — {{ product.name }} ({{ Number(product.interest_rate) }}% {{ product.interest_method.replace('_', ' ') }})
                        </option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="amount">Amount (UGX)</Label>
                    <Input id="amount" v-model="form.amount" type="number" step="0.01" min="1" required @blur="previewCost" />
                    <p v-if="form.errors.amount" class="text-xs text-destructive">{{ form.errors.amount }}</p>
                </div>
                <div class="grid gap-1.5">
                    <Label for="term">Term (months)</Label>
                    <Input id="term" v-model.number="form.term_months" type="number" min="1" required @blur="previewCost" />
                    <p v-if="form.errors.term_months" class="text-xs text-destructive">{{ form.errors.term_months }}</p>
                </div>
            </div>

            <div class="grid gap-1.5">
                <Label for="purpose">Purpose</Label>
                <Input id="purpose" v-model="form.purpose" placeholder="e.g. stock for retail shop" />
            </div>

            <!-- Cost of credit disclosure (FR-LNS-11) -->
            <div v-if="costPreview" class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm dark:border-blue-900 dark:bg-blue-950">
                <h2 class="mb-2 font-medium">Total Cost of Credit Disclosure</h2>
                <div class="grid gap-x-8 gap-y-1 sm:grid-cols-2 md:grid-cols-3">
                    <div class="flex justify-between"><span>Principal</span><span class="font-mono">{{ ugx(costPreview.principal) }}</span></div>
                    <div class="flex justify-between"><span>Total interest ({{ Number(costPreview.annual_rate) }}% {{ costPreview.interest_method.replace('_', ' ') }})</span><span class="font-mono">{{ ugx(costPreview.total_interest) }}</span></div>
                    <div class="flex justify-between"><span>Total fees</span><span class="font-mono">{{ ugx(costPreview.total_fees) }}</span></div>
                    <div class="flex justify-between font-medium"><span>Total cost of credit</span><span class="font-mono">{{ ugx(costPreview.total_cost) }}</span></div>
                    <div class="flex justify-between font-medium"><span>Total repayable</span><span class="font-mono">{{ ugx(costPreview.total_repayable) }}</span></div>
                    <div class="flex justify-between font-medium"><span>Net disbursed (after fees)</span><span class="font-mono">{{ ugx(costPreview.net_disbursed) }}</span></div>
                </div>
            </div>

            <!-- Guarantors -->
            <div class="grid gap-3">
                <div class="flex items-center justify-between">
                    <Label>Guarantors (savings pledge)</Label>
                    <Button type="button" variant="outline" size="sm" @click="form.guarantors.push({ member_id: '', savings_account_id: '', guaranteed_amount: '' })">
                        Add guarantor
                    </Button>
                </div>
                <div v-for="(guarantor, i) in form.guarantors" :key="i" class="grid items-end gap-3 rounded-lg border p-3 md:grid-cols-[1fr_1fr_1fr_auto]">
                    <div class="grid gap-1.5">
                        <Label class="text-xs">Guarantor member ID</Label>
                        <Input v-model="guarantor.member_id" required />
                    </div>
                    <div class="grid gap-1.5">
                        <Label class="text-xs">Savings account ID</Label>
                        <Input v-model="guarantor.savings_account_id" required />
                    </div>
                    <div class="grid gap-1.5">
                        <Label class="text-xs">Guaranteed amount</Label>
                        <Input v-model="guarantor.guaranteed_amount" type="number" step="0.01" min="1" required />
                    </div>
                    <Button type="button" variant="ghost" size="sm" class="text-destructive" @click="form.guarantors.splice(i, 1)">Remove</Button>
                </div>
            </div>

            <!-- Collateral -->
            <div class="grid gap-3">
                <div class="flex items-center justify-between">
                    <Label>Collateral</Label>
                    <Button type="button" variant="outline" size="sm" @click="form.collateral.push({ description: '', estimated_value: '' })">
                        Add collateral
                    </Button>
                </div>
                <div v-for="(item, i) in form.collateral" :key="i" class="grid items-end gap-3 rounded-lg border p-3 md:grid-cols-[2fr_1fr_auto]">
                    <div class="grid gap-1.5">
                        <Label class="text-xs">Description</Label>
                        <Input v-model="item.description" placeholder="e.g. Bajaj Boxer motorcycle UEB 123X" required />
                    </div>
                    <div class="grid gap-1.5">
                        <Label class="text-xs">Estimated value</Label>
                        <Input v-model="item.estimated_value" type="number" step="0.01" min="0" required />
                    </div>
                    <Button type="button" variant="ghost" size="sm" class="text-destructive" @click="form.collateral.splice(i, 1)">Remove</Button>
                </div>
            </div>

            <div class="flex gap-3">
                <Button type="submit" :disabled="form.processing">Submit application</Button>
                <Button type="button" variant="outline" @click="$inertia.visit('/loans')">Cancel</Button>
            </div>
        </form>
    </AppLayout>
</template>
