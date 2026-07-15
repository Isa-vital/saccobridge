<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Period {
    id: number;
    name: string;
    starts_on: string;
    ends_on: string;
    status: string;
    closed_by: string | null;
    closed_at: string | null;
    entries: number;
}

defineProps<{
    periods: Period[];
    can: { close: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Financial Periods', href: '/gl/periods' }];

const page = usePage();
const flash = computed(() => (page.props as any).flash ?? {});

const close = (period: Period) => {
    if (confirm(`Close period ${period.name}? No further entries can be posted to it.`)) {
        router.post(`/gl/periods/${period.id}/close`, {}, { preserveScroll: true });
    }
};

const reopen = (period: Period) => {
    if (confirm(`Reopen period ${period.name}?`)) {
        router.post(`/gl/periods/${period.id}/reopen`, {}, { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Financial Periods" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div v-if="flash.success" class="rounded-md bg-green-100 px-4 py-3 text-sm text-green-800 dark:bg-green-900 dark:text-green-200">
                {{ flash.success }}
            </div>
            <div v-if="flash.error" class="rounded-md bg-red-100 px-4 py-3 text-sm text-red-800 dark:bg-red-900 dark:text-red-200">
                {{ flash.error }}
            </div>

            <h1 class="text-xl font-semibold">Financial Periods</h1>
            <p class="text-sm text-muted-foreground">
                Periods are created automatically when the first entry of a month is posted. Closing a period locks it against further postings.
            </p>

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">Period</th>
                            <th class="px-4 py-3 font-medium">From</th>
                            <th class="px-4 py-3 font-medium">To</th>
                            <th class="px-4 py-3 text-right font-medium">Entries</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Closed By</th>
                            <th v-if="can.close" class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="period in periods" :key="period.id" class="border-b last:border-0 hover:bg-muted/50">
                            <td class="px-4 py-3 font-medium">{{ period.name }}</td>
                            <td class="px-4 py-3">{{ period.starts_on }}</td>
                            <td class="px-4 py-3">{{ period.ends_on }}</td>
                            <td class="px-4 py-3 text-right">{{ period.entries }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                    :class="period.status === 'open'
                                        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                        : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300'"
                                >
                                    {{ period.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ period.closed_by ?? '—' }}<span v-if="period.closed_at"> · {{ period.closed_at }}</span>
                            </td>
                            <td v-if="can.close" class="px-4 py-3 text-right">
                                <Button v-if="period.status === 'open'" variant="outline" size="sm" @click="close(period)">Close</Button>
                                <Button v-else variant="ghost" size="sm" @click="reopen(period)">Reopen</Button>
                            </td>
                        </tr>
                        <tr v-if="periods.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-muted-foreground">No periods yet — post the first journal entry.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
