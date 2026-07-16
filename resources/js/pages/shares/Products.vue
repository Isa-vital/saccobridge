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
    nominal_value: string;
    min_shares: number;
    max_shares: number | null;
    is_active: boolean;
    accounts_count: number;
}

defineProps<{
    products: Product[];
    equityAccounts: { id: number; code: string; name: string }[];
    can: { manage: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Share Products', href: '/shares/products' }];

const showForm = ref(false);
const form = useForm({
    code: '',
    name: '',
    nominal_value: '10000',
    min_shares: 1,
    max_shares: null as number | null,
    gl_equity_account_id: null as number | null,
});

const submit = () => {
    form.post('/shares/products', {
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
    <Head title="Share Products" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <FlashMessages />

            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold">Share Products</h1>
                <Button v-if="can.manage" @click="showForm = !showForm">{{ showForm ? 'Cancel' : 'New Product' }}</Button>
            </div>

            <form v-if="showForm" class="grid items-end gap-3 rounded-xl border p-4 md:grid-cols-6" @submit.prevent="submit">
                <div class="grid gap-1.5">
                    <Label for="code">Code</Label>
                    <Input id="code" v-model="form.code" placeholder="ORD" required />
                    <p v-if="form.errors.code" class="text-xs text-destructive">{{ form.errors.code }}</p>
                </div>
                <div class="grid gap-1.5">
                    <Label for="name">Name</Label>
                    <Input id="name" v-model="form.name" placeholder="Ordinary Shares" required />
                </div>
                <div class="grid gap-1.5">
                    <Label for="nominal">Nominal value</Label>
                    <Input id="nominal" v-model="form.nominal_value" type="number" step="0.01" min="1" required />
                </div>
                <div class="grid gap-1.5">
                    <Label for="min">Min shares</Label>
                    <Input id="min" v-model.number="form.min_shares" type="number" min="1" required />
                </div>
                <div class="grid gap-1.5">
                    <Label for="max">Max shares</Label>
                    <Input id="max" v-model.number="form.max_shares" type="number" min="1" placeholder="unlimited" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="equity">Equity GL account</Label>
                    <select id="equity" v-model="form.gl_equity_account_id" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm" required>
                        <option :value="null" disabled>Select…</option>
                        <option v-for="acc in equityAccounts" :key="acc.id" :value="acc.id">{{ acc.code }} — {{ acc.name }}</option>
                    </select>
                    <p v-if="form.errors.gl_equity_account_id" class="text-xs text-destructive">{{ form.errors.gl_equity_account_id }}</p>
                </div>
                <Button type="submit" :disabled="form.processing" class="md:col-span-6 md:w-fit">Create product</Button>
            </form>

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">Code</th>
                            <th class="px-4 py-3 font-medium">Product</th>
                            <th class="px-4 py-3 text-right font-medium">Nominal value</th>
                            <th class="px-4 py-3 text-right font-medium">Min</th>
                            <th class="px-4 py-3 text-right font-medium">Max</th>
                            <th class="px-4 py-3 text-right font-medium">Shareholders</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="product in products" :key="product.id" class="border-b last:border-0 hover:bg-muted/50">
                            <td class="px-4 py-3 font-mono">{{ product.code }}</td>
                            <td class="px-4 py-3">{{ product.name }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ ugx(product.nominal_value) }}</td>
                            <td class="px-4 py-3 text-right">{{ product.min_shares }}</td>
                            <td class="px-4 py-3 text-right">{{ product.max_shares ?? '∞' }}</td>
                            <td class="px-4 py-3 text-right">{{ product.accounts_count }}</td>
                            <td class="px-4 py-3">
                                <span :class="product.is_active ? 'text-green-600' : 'text-muted-foreground'">
                                    {{ product.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="products.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-muted-foreground">No share products yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
