<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Line {
    id: number;
    account: string;
    debit: string;
    credit: string;
    memo: string | null;
}

const props = defineProps<{
    entry: {
        id: number;
        reference: string;
        entry_date: string;
        description: string;
        status: string;
        posted_by: string | null;
        reversal_of: string | null;
        lines: Line[];
    };
    can: { reverse: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Journal', href: '/gl/journal' },
    { title: props.entry.reference, href: `/gl/journal/${props.entry.id}` },
];

const page = usePage();
const flash = computed(() => (page.props as any).flash ?? {});

const showReverse = ref(false);
const reason = ref('');

const reverse = () => {
    router.post(`/gl/journal/${props.entry.id}/reverse`, { reason: reason.value });
};

const ugx = (value: string) => (Number(value) === 0 ? '' : new Intl.NumberFormat('en-UG', { minimumFractionDigits: 2 }).format(Number(value)));
</script>

<template>
    <Head :title="entry.reference" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div v-if="flash.success" class="rounded-md bg-green-100 px-4 py-3 text-sm text-green-800 dark:bg-green-900 dark:text-green-200">
                {{ flash.success }}
            </div>
            <div v-if="flash.error" class="rounded-md bg-red-100 px-4 py-3 text-sm text-red-800 dark:bg-red-900 dark:text-red-200">
                {{ flash.error }}
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">{{ entry.reference }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ entry.entry_date }} · {{ entry.description }}
                        <span v-if="entry.posted_by"> · posted by {{ entry.posted_by }}</span>
                        <span v-if="entry.reversal_of"> · reversal of {{ entry.reversal_of }}</span>
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <span
                        class="rounded-full px-2.5 py-0.5 text-xs font-medium capitalize"
                        :class="entry.status === 'posted'
                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                            : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'"
                    >
                        {{ entry.status }}
                    </span>
                    <Button v-if="can.reverse && entry.status === 'posted'" variant="outline" @click="showReverse = !showReverse">
                        Reverse entry
                    </Button>
                </div>
            </div>

            <div v-if="showReverse" class="flex items-end gap-3 rounded-xl border p-4">
                <div class="grid flex-1 gap-1.5">
                    <label class="text-sm font-medium">Reason for reversal</label>
                    <Input v-model="reason" placeholder="e.g. posted with wrong amount" />
                </div>
                <Button :disabled="reason.length < 3" @click="reverse">Confirm reversal</Button>
            </div>

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">Account</th>
                            <th class="px-4 py-3 text-right font-medium">Debit</th>
                            <th class="px-4 py-3 text-right font-medium">Credit</th>
                            <th class="px-4 py-3 font-medium">Memo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="line in entry.lines" :key="line.id" class="border-b last:border-0">
                            <td class="px-4 py-3">{{ line.account }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ ugx(line.debit) }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ ugx(line.credit) }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ line.memo ?? '' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
