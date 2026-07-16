<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessages from '@/components/FlashMessages.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

interface RegisterRow {
    id: number;
    member: string;
    product: string;
    shares_count: number;
    nominal_value: string;
    value: string;
    status: string;
}

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
}

interface Product {
    id: number;
    code: string;
    name: string;
    nominal_value: string;
    min_shares: number;
    max_shares: number | null;
}

const props = defineProps<{
    accounts: Paginated<RegisterRow>;
    filters: { search?: string };
    totals: { shares: number; value: string };
    products: Product[];
    can: { post: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Share Register', href: '/shares/register' }];

const search = ref(props.filters.search ?? '');
let debounce: ReturnType<typeof setTimeout>;
watch(search, () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => router.get('/shares/register', { search: search.value || undefined }, { preserveState: true, replace: true }), 300);
});

const showBuy = ref(false);
const buyForm = useForm({
    member_id: '' as string | number,
    share_product_id: null as number | null,
    shares: 1,
});
const submitBuy = () => {
    buyForm.post('/shares/purchase', {
        preserveScroll: true,
        onSuccess: () => {
            buyForm.reset();
            showBuy.value = false;
        },
    });
};

// transfer / redeem inline actions
const actionAccount = ref<number | null>(null);
const actionType = ref<'transfer' | 'redeem' | null>(null);
const actionShares = ref(1);
const actionToMember = ref('');

const startAction = (id: number, type: 'transfer' | 'redeem') => {
    actionAccount.value = id;
    actionType.value = type;
    actionShares.value = 1;
    actionToMember.value = '';
};

const submitAction = () => {
    if (actionAccount.value === null || actionType.value === null) return;

    const url = `/shares/${actionAccount.value}/${actionType.value}`;
    const payload = actionType.value === 'transfer'
        ? { shares: actionShares.value, to_member_id: actionToMember.value }
        : { shares: actionShares.value };

    router.post(url, payload, {
        preserveScroll: true,
        onSuccess: () => {
            actionAccount.value = null;
            actionType.value = null;
        },
    });
};

const ugx = (value: string) => new Intl.NumberFormat('en-UG').format(Number(value));
</script>

<template>
    <Head title="Share Register" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <FlashMessages />

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Share Register</h1>
                    <p class="text-sm text-muted-foreground">
                        SACCO totals: <strong>{{ totals.shares.toLocaleString() }}</strong> shares ·
                        <strong class="font-mono">UGX {{ ugx(totals.value) }}</strong> share capital
                    </p>
                </div>
                <Button v-if="can.post" @click="showBuy = !showBuy">{{ showBuy ? 'Cancel' : 'Buy Shares' }}</Button>
            </div>

            <form v-if="showBuy" class="grid items-end gap-3 rounded-xl border p-4 md:grid-cols-4" @submit.prevent="submitBuy">
                <div class="grid gap-1.5">
                    <Label for="buy_member">Member ID</Label>
                    <Input id="buy_member" v-model="buyForm.member_id" placeholder="numeric member ID" required />
                    <p v-if="buyForm.errors.member_id" class="text-xs text-destructive">{{ buyForm.errors.member_id }}</p>
                </div>
                <div class="grid gap-1.5">
                    <Label for="buy_product">Product</Label>
                    <select id="buy_product" v-model="buyForm.share_product_id" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm" required>
                        <option :value="null" disabled>Select…</option>
                        <option v-for="product in products" :key="product.id" :value="product.id">
                            {{ product.code }} — {{ ugx(product.nominal_value) }}/share (min {{ product.min_shares }})
                        </option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="buy_shares">Number of shares</Label>
                    <Input id="buy_shares" v-model.number="buyForm.shares" type="number" min="1" required />
                    <p v-if="buyForm.errors.shares" class="text-xs text-destructive">{{ buyForm.errors.shares }}</p>
                </div>
                <Button type="submit" :disabled="buyForm.processing">Purchase</Button>
            </form>

            <Input v-model="search" placeholder="Search member name, member no, NIN…" class="max-w-sm" />

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">Member</th>
                            <th class="px-4 py-3 font-medium">Product</th>
                            <th class="px-4 py-3 text-right font-medium">Shares</th>
                            <th class="px-4 py-3 text-right font-medium">Nominal</th>
                            <th class="px-4 py-3 text-right font-medium">Value (UGX)</th>
                            <th v-if="can.post" class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="row in accounts.data" :key="row.id">
                            <tr class="border-b last:border-0 hover:bg-muted/50">
                                <td class="px-4 py-3">{{ row.member }}</td>
                                <td class="px-4 py-3 font-mono">{{ row.product }}</td>
                                <td class="px-4 py-3 text-right font-mono">{{ row.shares_count.toLocaleString() }}</td>
                                <td class="px-4 py-3 text-right font-mono">{{ ugx(row.nominal_value) }}</td>
                                <td class="px-4 py-3 text-right font-mono font-medium">{{ ugx(row.value) }}</td>
                                <td v-if="can.post" class="px-4 py-3 text-right">
                                    <Button variant="ghost" size="sm" @click="startAction(row.id, 'transfer')">Transfer</Button>
                                    <Button variant="ghost" size="sm" class="text-destructive" @click="startAction(row.id, 'redeem')">Redeem</Button>
                                </td>
                            </tr>
                            <tr v-if="actionAccount === row.id" class="border-b bg-muted/30">
                                <td colspan="6" class="px-4 py-3">
                                    <form class="flex flex-wrap items-end gap-3" @submit.prevent="submitAction">
                                        <div class="grid gap-1">
                                            <Label class="text-xs">Shares</Label>
                                            <Input v-model.number="actionShares" type="number" min="1" class="w-28" required />
                                        </div>
                                        <div v-if="actionType === 'transfer'" class="grid gap-1">
                                            <Label class="text-xs">Recipient member ID</Label>
                                            <Input v-model="actionToMember" class="w-40" required />
                                        </div>
                                        <Button type="submit" size="sm">
                                            Confirm {{ actionType }}
                                        </Button>
                                        <Button type="button" size="sm" variant="ghost" @click="actionAccount = null">Cancel</Button>
                                    </form>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="accounts.data.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">No shareholders yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap gap-1" v-if="accounts.links.length > 3">
                <template v-for="(link, i) in accounts.links" :key="i">
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
