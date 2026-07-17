<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessages from '@/components/FlashMessages.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

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
    application_fee: string;
    processing_fee_percent: string;
    penalty_rate: string;
    required_guarantors: number;
    savings_multiple: string | null;
    is_active: boolean;
    loans_count: number;
}

defineProps<{
    products: Product[];
    glAccounts: { asset: { id: number; code: string; name: string }[]; income: { id: number; code: string; name: string }[] };
    can: { manage: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Loan Products', href: '/loans/products' }];

const showForm = ref(false);
const form = useForm({
    code: '',
    name: '',
    interest_rate: '24',
    interest_method: 'reducing_balance',
    min_term_months: 1,
    max_term_months: 24,
    min_amount: '100000',
    max_amount: null as string | null,
    grace_period_days: 0,
    application_fee: '0',
    processing_fee_percent: '1',
    penalty_rate: '2',
    required_guarantors: 1,
    savings_multiple: '3',
    gl_portfolio_account_id: null as number | null,
    gl_interest_income_account_id: null as number | null,
    gl_fee_income_account_id: null as number | null,
    gl_penalty_income_account_id: null as number | null,
});

const submit = () => {
    form.post('/loans/products', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            showForm.value = false;
        },
    });
};

const ugx = (value: string | null) => (value === null ? '∞' : new Intl.NumberFormat('en-UG').format(Number(value)));
</script>

<template>
    <Head title="Loan Products" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <FlashMessages />

            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold">Loan Products</h1>
                <Button v-if="can.manage" @click="showForm = !showForm">{{ showForm ? 'Cancel' : 'New Product' }}</Button>
            </div>

            <form v-if="showForm" class="grid gap-4 rounded-xl border p-4 md:grid-cols-4" @submit.prevent="submit">
                <div class="grid gap-1.5"><Label>Code</Label><Input v-model="form.code" placeholder="BIZ" required /><p v-if="form.errors.code" class="text-xs text-destructive">{{ form.errors.code }}</p></div>
                <div class="grid gap-1.5"><Label>Name</Label><Input v-model="form.name" placeholder="Business Loan" required /></div>
                <div class="grid gap-1.5"><Label>Interest rate (% p.a.)</Label><Input v-model="form.interest_rate" type="number" step="0.000001" min="0" required /></div>
                <div class="grid gap-1.5">
                    <Label>Interest method</Label>
                    <select v-model="form.interest_method" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm">
                        <option value="reducing_balance">Reducing balance</option>
                        <option value="flat">Flat</option>
                    </select>
                </div>
                <div class="grid gap-1.5"><Label>Min term (months)</Label><Input v-model.number="form.min_term_months" type="number" min="1" required /></div>
                <div class="grid gap-1.5"><Label>Max term (months)</Label><Input v-model.number="form.max_term_months" type="number" min="1" required /></div>
                <div class="grid gap-1.5"><Label>Min amount</Label><Input v-model="form.min_amount" type="number" step="0.01" min="0" required /></div>
                <div class="grid gap-1.5"><Label>Max amount</Label><Input v-model="form.max_amount" type="number" step="0.01" placeholder="unlimited" /></div>
                <div class="grid gap-1.5"><Label>Grace period (days)</Label><Input v-model.number="form.grace_period_days" type="number" min="0" required /></div>
                <div class="grid gap-1.5"><Label>Application fee (fixed)</Label><Input v-model="form.application_fee" type="number" step="0.01" min="0" required /></div>
                <div class="grid gap-1.5"><Label>Processing fee (%)</Label><Input v-model="form.processing_fee_percent" type="number" step="0.000001" min="0" required /></div>
                <div class="grid gap-1.5"><Label>Penalty rate (%/month)</Label><Input v-model="form.penalty_rate" type="number" step="0.000001" min="0" required /></div>
                <div class="grid gap-1.5"><Label>Required guarantors</Label><Input v-model.number="form.required_guarantors" type="number" min="0" required /></div>
                <div class="grid gap-1.5"><Label>Savings multiple (× savings)</Label><Input v-model="form.savings_multiple" type="number" step="0.1" placeholder="none" /></div>
                <div class="grid gap-1.5">
                    <Label>GL portfolio (asset)</Label>
                    <select v-model="form.gl_portfolio_account_id" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm" required>
                        <option :value="null" disabled>Select…</option>
                        <option v-for="acc in glAccounts.asset" :key="acc.id" :value="acc.id">{{ acc.code }} — {{ acc.name }}</option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label>GL interest income</Label>
                    <select v-model="form.gl_interest_income_account_id" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm" required>
                        <option :value="null" disabled>Select…</option>
                        <option v-for="acc in glAccounts.income" :key="acc.id" :value="acc.id">{{ acc.code }} — {{ acc.name }}</option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label>GL fee income</Label>
                    <select v-model="form.gl_fee_income_account_id" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm" required>
                        <option :value="null" disabled>Select…</option>
                        <option v-for="acc in glAccounts.income" :key="acc.id" :value="acc.id">{{ acc.code }} — {{ acc.name }}</option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label>GL penalty income</Label>
                    <select v-model="form.gl_penalty_income_account_id" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm" required>
                        <option :value="null" disabled>Select…</option>
                        <option v-for="acc in glAccounts.income" :key="acc.id" :value="acc.id">{{ acc.code }} — {{ acc.name }}</option>
                    </select>
                </div>
                <Button type="submit" :disabled="form.processing" class="md:col-span-4 md:w-fit">Create product</Button>
            </form>

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">Code</th>
                            <th class="px-4 py-3 font-medium">Product</th>
                            <th class="px-4 py-3 text-right font-medium">Rate</th>
                            <th class="px-4 py-3 font-medium">Method</th>
                            <th class="px-4 py-3 font-medium">Term</th>
                            <th class="px-4 py-3 text-right font-medium">Amount range</th>
                            <th class="px-4 py-3 text-right font-medium">Guarantors</th>
                            <th class="px-4 py-3 text-right font-medium">× Savings</th>
                            <th class="px-4 py-3 text-right font-medium">Loans</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="product in products" :key="product.id" class="border-b last:border-0 hover:bg-muted/50">
                            <td class="px-4 py-3 font-mono">{{ product.code }}</td>
                            <td class="px-4 py-3">{{ product.name }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ Number(product.interest_rate) }}%</td>
                            <td class="px-4 py-3 capitalize">{{ product.interest_method.replace('_', ' ') }}</td>
                            <td class="px-4 py-3">{{ product.min_term_months }}–{{ product.max_term_months }}m</td>
                            <td class="px-4 py-3 text-right font-mono">{{ ugx(product.min_amount) }} – {{ ugx(product.max_amount) }}</td>
                            <td class="px-4 py-3 text-right">{{ product.required_guarantors }}</td>
                            <td class="px-4 py-3 text-right">{{ product.savings_multiple ? Number(product.savings_multiple) + '×' : '—' }}</td>
                            <td class="px-4 py-3 text-right">{{ product.loans_count }}</td>
                        </tr>
                        <tr v-if="products.length === 0">
                            <td colspan="9" class="px-4 py-10 text-center text-muted-foreground">No loan products yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
