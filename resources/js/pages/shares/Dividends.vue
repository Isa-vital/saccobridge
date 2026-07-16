<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessages from '@/components/FlashMessages.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface DividendRow {
    id: number;
    financial_year: string;
    product: string;
    rate: string;
    total_declared: string;
    status: string;
    declared_by: string;
    declared_by_id: number;
    approved_by: string | null;
    payouts_count: number;
    distributed_at: string | null;
}

defineProps<{
    dividends: DividendRow[];
    products: { id: number; code: string; name: string }[];
    can: { declare: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dividends', href: '/shares/dividends' }];

const page = usePage();
const myId = computed(() => (page.props as any).auth?.user?.id);

const showDeclare = ref(false);
const form = useForm({
    financial_year: String(new Date().getFullYear()),
    share_product_id: null as number | null,
    rate: '',
});

const submit = () => {
    form.post('/shares/dividends', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            showDeclare.value = false;
        },
    });
};

const approve = (dividend: DividendRow) => {
    if (confirm(`Approve dividend FY${dividend.financial_year} of ${dividend.total_declared}?`)) {
        router.post(`/shares/dividends/${dividend.id}/approve`, {}, { preserveScroll: true });
    }
};

const distribute = (dividend: DividendRow) => {
    if (confirm(`Distribute dividend FY${dividend.financial_year} pro-rata to all shareholders? This posts to member accounts.`)) {
        router.post(`/shares/dividends/${dividend.id}/distribute`, {}, { preserveScroll: true });
    }
};

const ugx = (value: string) => new Intl.NumberFormat('en-UG').format(Number(value));

const statusClasses: Record<string, string> = {
    declared: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    approved: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    distributed: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
};
</script>

<template>
    <Head title="Dividends" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <FlashMessages />

            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold">Dividends</h1>
                <Button v-if="can.declare" @click="showDeclare = !showDeclare">{{ showDeclare ? 'Cancel' : 'Declare Dividend' }}</Button>
            </div>

            <form v-if="showDeclare" class="grid items-end gap-3 rounded-xl border p-4 md:grid-cols-4" @submit.prevent="submit">
                <div class="grid gap-1.5">
                    <Label for="fy">Financial year</Label>
                    <Input id="fy" v-model="form.financial_year" required />
                </div>
                <div class="grid gap-1.5">
                    <Label for="product">Share product</Label>
                    <select id="product" v-model="form.share_product_id" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm" required>
                        <option :value="null" disabled>Select…</option>
                        <option v-for="product in products" :key="product.id" :value="product.id">{{ product.code }} — {{ product.name }}</option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="rate">Rate (% of share value)</Label>
                    <Input id="rate" v-model="form.rate" type="number" step="0.000001" min="0.000001" max="100" required />
                    <p v-if="form.errors.rate" class="text-xs text-destructive">{{ form.errors.rate }}</p>
                </div>
                <Button type="submit" :disabled="form.processing">Declare</Button>
            </form>

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">FY</th>
                            <th class="px-4 py-3 font-medium">Product</th>
                            <th class="px-4 py-3 text-right font-medium">Rate %</th>
                            <th class="px-4 py-3 text-right font-medium">Total (UGX)</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Declared / Approved by</th>
                            <th class="px-4 py-3 text-right font-medium">Payouts</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="dividend in dividends" :key="dividend.id" class="border-b last:border-0 hover:bg-muted/50">
                            <td class="px-4 py-3 font-mono">{{ dividend.financial_year }}</td>
                            <td class="px-4 py-3 font-mono">{{ dividend.product }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ Number(dividend.rate) }}</td>
                            <td class="px-4 py-3 text-right font-mono font-medium">{{ ugx(dividend.total_declared) }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="statusClasses[dividend.status]">
                                    {{ dividend.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ dividend.declared_by }}<span v-if="dividend.approved_by"> / {{ dividend.approved_by }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">{{ dividend.payouts_count }}</td>
                            <td class="px-4 py-3 text-right">
                                <template v-if="dividend.status === 'declared' && can.declare">
                                    <span v-if="dividend.declared_by_id === myId" class="text-xs text-muted-foreground">
                                        awaiting another officer (maker-checker)
                                    </span>
                                    <Button v-else size="sm" @click="approve(dividend)">Approve</Button>
                                </template>
                                <Button
                                    v-else-if="dividend.status === 'approved' && can.declare"
                                    size="sm"
                                    @click="distribute(dividend)"
                                >
                                    Distribute
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="dividends.length === 0">
                            <td colspan="8" class="px-4 py-10 text-center text-muted-foreground">No dividends declared yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
