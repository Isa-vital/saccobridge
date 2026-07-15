<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessages from '@/components/FlashMessages.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Product {
    id: number;
    code: string;
    name: string;
    interest_rate: string;
    interest_basis: string;
    interest_posting: string;
    min_opening_deposit: string;
    min_balance: string;
    withdrawal_fee: string;
    max_withdrawals_per_month: number | null;
    is_active: boolean;
    accounts_count: number;
}

interface GlOption {
    id: number;
    code: string;
    name: string;
}

defineProps<{
    products: Product[];
    glAccounts: { liability: GlOption[]; expense: GlOption[] };
    can: { manage: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Savings Products', href: '/savings/products' }];

const showForm = ref(false);
const form = useForm({
    code: '',
    name: '',
    interest_rate: '5',
    interest_basis: 'daily_balance',
    interest_posting: 'monthly',
    min_opening_deposit: '0',
    min_balance: '0',
    withdrawal_fee: '0',
    max_withdrawals_per_month: null as number | null,
    gl_liability_account_id: null as number | null,
    gl_interest_expense_account_id: null as number | null,
});

const submit = () => {
    form.post('/savings/products', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            showForm.value = false;
        },
    });
};

const ugx = (value: string) => new Intl.NumberFormat('en-UG').format(Number(value));
</script>

<template>
    <Head title="Savings Products" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <FlashMessages />

            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold">Savings Products</h1>
                <Button v-if="can.manage" @click="showForm = !showForm">{{ showForm ? 'Cancel' : 'New Product' }}</Button>
            </div>

            <form v-if="showForm" class="grid gap-4 rounded-xl border p-4 md:grid-cols-3" @submit.prevent="submit">
                <div class="grid gap-1.5">
                    <Label for="code">Code</Label>
                    <Input id="code" v-model="form.code" placeholder="ORD" required />
                    <p v-if="form.errors.code" class="text-xs text-destructive">{{ form.errors.code }}</p>
                </div>
                <div class="grid gap-1.5">
                    <Label for="name">Name</Label>
                    <Input id="name" v-model="form.name" placeholder="Ordinary Savings" required />
                    <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
                </div>
                <div class="grid gap-1.5">
                    <Label for="interest_rate">Interest rate (% p.a.)</Label>
                    <Input id="interest_rate" v-model="form.interest_rate" type="number" step="0.000001" min="0" required />
                    <p v-if="form.errors.interest_rate" class="text-xs text-destructive">{{ form.errors.interest_rate }}</p>
                </div>
                <div class="grid gap-1.5">
                    <Label for="interest_basis">Interest basis</Label>
                    <select id="interest_basis" v-model="form.interest_basis" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm">
                        <option value="daily_balance">Daily balance</option>
                        <option value="monthly_min_balance">Monthly minimum balance</option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="interest_posting">Interest posting</Label>
                    <select id="interest_posting" v-model="form.interest_posting" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm">
                        <option value="monthly">Monthly</option>
                        <option value="quarterly">Quarterly</option>
                        <option value="annually">Annually</option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="max_withdrawals">Max withdrawals / month</Label>
                    <Input id="max_withdrawals" v-model.number="form.max_withdrawals_per_month" type="number" min="1" placeholder="unlimited" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="min_opening">Min opening deposit</Label>
                    <Input id="min_opening" v-model="form.min_opening_deposit" type="number" step="0.01" min="0" required />
                </div>
                <div class="grid gap-1.5">
                    <Label for="min_balance">Min balance</Label>
                    <Input id="min_balance" v-model="form.min_balance" type="number" step="0.01" min="0" required />
                </div>
                <div class="grid gap-1.5">
                    <Label for="withdrawal_fee">Withdrawal fee</Label>
                    <Input id="withdrawal_fee" v-model="form.withdrawal_fee" type="number" step="0.01" min="0" required />
                </div>
                <div class="grid gap-1.5">
                    <Label for="gl_liability">GL liability account</Label>
                    <select id="gl_liability" v-model="form.gl_liability_account_id" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm" required>
                        <option :value="null" disabled>Select…</option>
                        <option v-for="acc in glAccounts.liability" :key="acc.id" :value="acc.id">{{ acc.code }} — {{ acc.name }}</option>
                    </select>
                    <p v-if="form.errors.gl_liability_account_id" class="text-xs text-destructive">{{ form.errors.gl_liability_account_id }}</p>
                </div>
                <div class="grid gap-1.5">
                    <Label for="gl_expense">GL interest expense account</Label>
                    <select id="gl_expense" v-model="form.gl_interest_expense_account_id" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm" required>
                        <option :value="null" disabled>Select…</option>
                        <option v-for="acc in glAccounts.expense" :key="acc.id" :value="acc.id">{{ acc.code }} — {{ acc.name }}</option>
                    </select>
                    <p v-if="form.errors.gl_interest_expense_account_id" class="text-xs text-destructive">{{ form.errors.gl_interest_expense_account_id }}</p>
                </div>
                <div class="flex items-end">
                    <Button type="submit" :disabled="form.processing">Create product</Button>
                </div>
            </form>

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">Code</th>
                            <th class="px-4 py-3 font-medium">Product</th>
                            <th class="px-4 py-3 text-right font-medium">Rate % p.a.</th>
                            <th class="px-4 py-3 text-right font-medium">Min opening</th>
                            <th class="px-4 py-3 text-right font-medium">Min balance</th>
                            <th class="px-4 py-3 text-right font-medium">W/D fee</th>
                            <th class="px-4 py-3 text-right font-medium">Accounts</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th v-if="can.manage" class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="product in products" :key="product.id" class="border-b last:border-0 hover:bg-muted/50">
                            <td class="px-4 py-3 font-mono">{{ product.code }}</td>
                            <td class="px-4 py-3">{{ product.name }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ Number(product.interest_rate) }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ ugx(product.min_opening_deposit) }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ ugx(product.min_balance) }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ ugx(product.withdrawal_fee) }}</td>
                            <td class="px-4 py-3 text-right">{{ product.accounts_count }}</td>
                            <td class="px-4 py-3">
                                <span :class="product.is_active ? 'text-green-600' : 'text-muted-foreground'">
                                    {{ product.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td v-if="can.manage" class="px-4 py-3 text-right">
                                <Button variant="ghost" size="sm" @click="router.post(`/savings/products/${product.id}/toggle`, {}, { preserveScroll: true })">
                                    {{ product.is_active ? 'Deactivate' : 'Activate' }}
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="products.length === 0">
                            <td colspan="9" class="px-4 py-10 text-center text-muted-foreground">No products defined yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
