<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Account {
    id: number;
    code: string;
    name: string;
    type: string;
    parent_id: number | null;
    is_system: boolean;
    is_active: boolean;
    balance: string;
}

const props = defineProps<{
    accounts: Account[];
    can: { manage: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Chart of Accounts', href: '/gl/accounts' }];

const page = usePage();
const flash = computed(() => (page.props as any).flash ?? {});

const parents = computed(() => props.accounts.filter((a) => a.parent_id === null));
const childrenOf = (id: number) => props.accounts.filter((a) => a.parent_id === id);

const showForm = ref(false);
const form = useForm({
    code: '',
    name: '',
    type: 'asset',
    parent_id: null as number | null,
});

const submit = () => {
    form.post('/gl/accounts', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            showForm.value = false;
        },
    });
};

const ugx = (value: string) => new Intl.NumberFormat('en-UG').format(Number(value));

const typeClasses: Record<string, string> = {
    asset: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    liability: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
    equity: 'bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-200',
    income: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    expense: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
};
</script>

<template>
    <Head title="Chart of Accounts" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div v-if="flash.success" class="rounded-md bg-green-100 px-4 py-3 text-sm text-green-800 dark:bg-green-900 dark:text-green-200">
                {{ flash.success }}
            </div>
            <div v-if="flash.error" class="rounded-md bg-red-100 px-4 py-3 text-sm text-red-800 dark:bg-red-900 dark:text-red-200">
                {{ flash.error }}
            </div>

            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold">Chart of Accounts</h1>
                <Button v-if="can.manage" @click="showForm = !showForm">{{ showForm ? 'Cancel' : 'New Account' }}</Button>
            </div>

            <!-- New account form -->
            <form v-if="showForm" class="grid items-end gap-3 rounded-xl border p-4 md:grid-cols-5" @submit.prevent="submit">
                <div class="grid gap-1.5">
                    <Label for="code">Code</Label>
                    <Input id="code" v-model="form.code" placeholder="e.g. 1050" required />
                    <p v-if="form.errors.code" class="text-xs text-destructive">{{ form.errors.code }}</p>
                </div>
                <div class="grid gap-1.5">
                    <Label for="name">Name</Label>
                    <Input id="name" v-model="form.name" required />
                    <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
                </div>
                <div class="grid gap-1.5">
                    <Label for="type">Type</Label>
                    <select id="type" v-model="form.type" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs">
                        <option value="asset">Asset</option>
                        <option value="liability">Liability</option>
                        <option value="equity">Equity</option>
                        <option value="income">Income</option>
                        <option value="expense">Expense</option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="parent">Parent</Label>
                    <select id="parent" v-model="form.parent_id" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs">
                        <option :value="null">— top level —</option>
                        <option v-for="parent in parents" :key="parent.id" :value="parent.id">{{ parent.code }} {{ parent.name }}</option>
                    </select>
                </div>
                <Button type="submit" :disabled="form.processing">Create</Button>
            </form>

            <!-- Tree -->
            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">Code</th>
                            <th class="px-4 py-3 font-medium">Account</th>
                            <th class="px-4 py-3 font-medium">Type</th>
                            <th class="px-4 py-3 text-right font-medium">Balance (UGX)</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th v-if="can.manage" class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="parent in parents" :key="parent.id">
                            <tr class="border-b bg-muted/30 font-medium">
                                <td class="px-4 py-2.5 font-mono">{{ parent.code }}</td>
                                <td class="px-4 py-2.5">{{ parent.name }}</td>
                                <td class="px-4 py-2.5">
                                    <span class="rounded-full px-2 py-0.5 text-xs capitalize" :class="typeClasses[parent.type]">{{ parent.type }}</span>
                                </td>
                                <td class="px-4 py-2.5 text-right font-mono">{{ ugx(parent.balance) }}</td>
                                <td class="px-4 py-2.5"></td>
                                <td v-if="can.manage"></td>
                            </tr>
                            <tr v-for="child in childrenOf(parent.id)" :key="child.id" class="border-b last:border-0 hover:bg-muted/50">
                                <td class="px-4 py-2.5 pl-8 font-mono">{{ child.code }}</td>
                                <td class="px-4 py-2.5">
                                    <Link :href="`/gl/accounts/${child.id}/ledger`" class="hover:underline">{{ child.name }}</Link>
                                </td>
                                <td class="px-4 py-2.5">
                                    <span class="rounded-full px-2 py-0.5 text-xs capitalize" :class="typeClasses[child.type]">{{ child.type }}</span>
                                </td>
                                <td class="px-4 py-2.5 text-right font-mono">{{ ugx(child.balance) }}</td>
                                <td class="px-4 py-2.5">
                                    <span :class="child.is_active ? 'text-green-600' : 'text-muted-foreground'">
                                        {{ child.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                    <span v-if="child.is_system" class="ml-1 text-xs text-muted-foreground">(system)</span>
                                </td>
                                <td v-if="can.manage" class="px-4 py-2.5 text-right">
                                    <Button
                                        v-if="!child.is_system"
                                        variant="ghost"
                                        size="sm"
                                        @click="router.post(`/gl/accounts/${child.id}/toggle`, {}, { preserveScroll: true })"
                                    >
                                        {{ child.is_active ? 'Deactivate' : 'Activate' }}
                                    </Button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
