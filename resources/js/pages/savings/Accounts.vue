<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessages from '@/components/FlashMessages.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

interface AccountRow {
    id: number;
    account_no: string;
    member: string;
    product: string;
    balance: string;
    blocked_amount: string;
    status: string;
    opened_at: string;
}

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
}

const props = defineProps<{
    accounts: Paginated<AccountRow>;
    filters: { search?: string };
    products: { id: number; code: string; name: string; min_opening_deposit: string }[];
    can: { open: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Savings Accounts', href: '/savings/accounts' }];

const search = ref(props.filters.search ?? '');
let debounce: ReturnType<typeof setTimeout>;
watch(search, () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => router.get('/savings/accounts', { search: search.value || undefined }, { preserveState: true, replace: true }), 300);
});

const showOpen = ref(false);
const openForm = useForm({
    member_id: '' as string | number,
    savings_product_id: null as number | null,
    opening_deposit: '0',
});

const submitOpen = () => {
    openForm.post('/savings/accounts', {
        preserveScroll: true,
        onSuccess: () => {
            openForm.reset();
            showOpen.value = false;
        },
    });
};

const ugx = (value: string) => new Intl.NumberFormat('en-UG').format(Number(value));

const statusClasses: Record<string, string> = {
    active: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    dormant: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
    closed: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
};
</script>

<template>
    <Head title="Savings Accounts" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <FlashMessages />

            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-xl font-semibold">Savings Accounts</h1>
                <Button v-if="can.open" @click="showOpen = !showOpen">{{ showOpen ? 'Cancel' : 'Open Account' }}</Button>
            </div>

            <form v-if="showOpen" class="grid items-end gap-3 rounded-xl border p-4 md:grid-cols-4" @submit.prevent="submitOpen">
                <div class="grid gap-1.5">
                    <Label for="member_id">Member ID</Label>
                    <Input id="member_id" v-model="openForm.member_id" placeholder="numeric member ID" required />
                    <p v-if="openForm.errors.member_id" class="text-xs text-destructive">{{ openForm.errors.member_id }}</p>
                </div>
                <div class="grid gap-1.5">
                    <Label for="product_id">Product</Label>
                    <select id="product_id" v-model="openForm.savings_product_id" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm" required>
                        <option :value="null" disabled>Select…</option>
                        <option v-for="product in products" :key="product.id" :value="product.id">
                            {{ product.code }} — {{ product.name }} (min {{ ugx(product.min_opening_deposit) }})
                        </option>
                    </select>
                    <p v-if="openForm.errors.savings_product_id" class="text-xs text-destructive">{{ openForm.errors.savings_product_id }}</p>
                </div>
                <div class="grid gap-1.5">
                    <Label for="opening_deposit">Opening deposit</Label>
                    <Input id="opening_deposit" v-model="openForm.opening_deposit" type="number" step="0.01" min="0" required />
                    <p v-if="openForm.errors.opening_deposit" class="text-xs text-destructive">{{ openForm.errors.opening_deposit }}</p>
                </div>
                <Button type="submit" :disabled="openForm.processing">Open account</Button>
            </form>

            <Input v-model="search" placeholder="Search account no, member name, member no…" class="max-w-sm" />

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">Account No</th>
                            <th class="px-4 py-3 font-medium">Member</th>
                            <th class="px-4 py-3 font-medium">Product</th>
                            <th class="px-4 py-3 text-right font-medium">Balance</th>
                            <th class="px-4 py-3 text-right font-medium">Blocked</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Opened</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="account in accounts.data"
                            :key="account.id"
                            class="cursor-pointer border-b transition-colors last:border-0 hover:bg-muted/50"
                            @click="router.visit(`/savings/accounts/${account.id}`)"
                        >
                            <td class="px-4 py-3 font-mono">{{ account.account_no }}</td>
                            <td class="px-4 py-3">{{ account.member }}</td>
                            <td class="px-4 py-3">{{ account.product }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ ugx(account.balance) }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ ugx(account.blocked_amount) }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="statusClasses[account.status]">
                                    {{ account.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3">{{ account.opened_at }}</td>
                        </tr>
                        <tr v-if="accounts.data.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-muted-foreground">No savings accounts found.</td>
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
