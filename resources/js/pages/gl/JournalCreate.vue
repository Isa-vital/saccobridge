<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed } from 'vue';

interface Account {
    id: number;
    code: string;
    name: string;
    type: string;
}

defineProps<{ accounts: Account[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Journal', href: '/gl/journal' },
    { title: 'New Entry', href: '/gl/journal/create' },
];

interface LineRow {
    account_id: number | null;
    debit: string;
    credit: string;
    memo: string;
}

const emptyLine = (): LineRow => ({ account_id: null, debit: '', credit: '', memo: '' });

const form = useForm({
    entry_date: new Date().toISOString().substring(0, 10),
    description: '',
    lines: [emptyLine(), emptyLine()] as LineRow[],
});

const totalDebit = computed(() => form.lines.reduce((sum, line) => sum + (Number(line.debit) || 0), 0));
const totalCredit = computed(() => form.lines.reduce((sum, line) => sum + (Number(line.credit) || 0), 0));
const balanced = computed(() => totalDebit.value > 0 && Math.abs(totalDebit.value - totalCredit.value) < 0.005);

const ugx = (value: number) => new Intl.NumberFormat('en-UG', { minimumFractionDigits: 2 }).format(value);

const submit = () => form.post('/gl/journal');
</script>

<template>
    <Head title="New Journal Entry" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <form class="flex flex-col gap-5 p-4" @submit.prevent="submit">
            <h1 class="text-xl font-semibold">New Journal Entry</h1>

            <div class="grid gap-4 md:grid-cols-3">
                <div class="grid gap-1.5">
                    <Label for="entry_date">Entry date</Label>
                    <Input id="entry_date" v-model="form.entry_date" type="date" required />
                    <p v-if="form.errors.entry_date" class="text-xs text-destructive">{{ form.errors.entry_date }}</p>
                </div>
                <div class="grid gap-1.5 md:col-span-2">
                    <Label for="description">Description</Label>
                    <Input id="description" v-model="form.description" placeholder="e.g. Office rent for July" required />
                    <p v-if="form.errors.description" class="text-xs text-destructive">{{ form.errors.description }}</p>
                </div>
            </div>

            <!-- Lines -->
            <div class="rounded-xl border">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-3 py-2 font-medium">Account</th>
                            <th class="w-36 px-3 py-2 font-medium">Debit</th>
                            <th class="w-36 px-3 py-2 font-medium">Credit</th>
                            <th class="px-3 py-2 font-medium">Memo</th>
                            <th class="w-16"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(line, i) in form.lines" :key="i" class="border-b last:border-0">
                            <td class="px-3 py-2">
                                <select
                                    v-model="line.account_id"
                                    class="h-9 w-full rounded-md border border-input bg-transparent px-2 text-sm"
                                    required
                                >
                                    <option :value="null" disabled>Select account…</option>
                                    <option v-for="account in accounts" :key="account.id" :value="account.id">
                                        {{ account.code }} — {{ account.name }}
                                    </option>
                                </select>
                            </td>
                            <td class="px-3 py-2">
                                <Input v-model="line.debit" type="number" step="0.01" min="0" :disabled="Number(line.credit) > 0" />
                            </td>
                            <td class="px-3 py-2">
                                <Input v-model="line.credit" type="number" step="0.01" min="0" :disabled="Number(line.debit) > 0" />
                            </td>
                            <td class="px-3 py-2">
                                <Input v-model="line.memo" placeholder="optional" />
                            </td>
                            <td class="px-3 py-2 text-right">
                                <Button
                                    v-if="form.lines.length > 2"
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="text-destructive"
                                    @click="form.lines.splice(i, 1)"
                                >
                                    ✕
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t bg-muted/30 font-medium">
                            <td class="px-3 py-2 text-right">Totals</td>
                            <td class="px-3 py-2 font-mono">{{ ugx(totalDebit) }}</td>
                            <td class="px-3 py-2 font-mono">{{ ugx(totalCredit) }}</td>
                            <td colspan="2" class="px-3 py-2">
                                <span v-if="balanced" class="text-green-600">✓ Balanced</span>
                                <span v-else class="text-destructive">Not balanced</span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <p v-if="form.errors.lines" class="text-sm text-destructive">{{ form.errors.lines }}</p>

            <div class="flex gap-3">
                <Button type="button" variant="outline" @click="form.lines.push(emptyLine())">Add line</Button>
                <Button type="submit" :disabled="form.processing || !balanced">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    Post entry
                </Button>
            </div>
        </form>
    </AppLayout>
</template>
