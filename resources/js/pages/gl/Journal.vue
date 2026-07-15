<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

interface EntryRow {
    id: number;
    reference: string;
    entry_date: string;
    description: string;
    status: string;
    posted_by: string | null;
    is_reversal: boolean;
}

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
}

const props = defineProps<{
    entries: Paginated<EntryRow>;
    filters: { search?: string };
    can: { post: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Journal', href: '/gl/journal' }];

const search = ref(props.filters.search ?? '');
let debounce: ReturnType<typeof setTimeout>;
watch(search, () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => router.get('/gl/journal', { search: search.value || undefined }, { preserveState: true, replace: true }), 300);
});
</script>

<template>
    <Head title="Journal" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-xl font-semibold">Journal Entries</h1>
                <Link v-if="can.post" href="/gl/journal/create">
                    <Button>New Journal Entry</Button>
                </Link>
            </div>

            <Input v-model="search" placeholder="Search reference or description…" class="max-w-sm" />

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">Reference</th>
                            <th class="px-4 py-3 font-medium">Date</th>
                            <th class="px-4 py-3 font-medium">Description</th>
                            <th class="px-4 py-3 font-medium">Posted By</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="entry in entries.data"
                            :key="entry.id"
                            class="cursor-pointer border-b transition-colors last:border-0 hover:bg-muted/50"
                            @click="router.visit(`/gl/journal/${entry.id}`)"
                        >
                            <td class="px-4 py-3 font-mono">{{ entry.reference }}</td>
                            <td class="px-4 py-3">{{ entry.entry_date }}</td>
                            <td class="px-4 py-3">
                                {{ entry.description }}
                                <span v-if="entry.is_reversal" class="ml-1 rounded-full bg-muted px-2 py-0.5 text-xs">reversal</span>
                            </td>
                            <td class="px-4 py-3">{{ entry.posted_by ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                    :class="entry.status === 'posted'
                                        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                        : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'"
                                >
                                    {{ entry.status }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="entries.data.length === 0">
                            <td colspan="5" class="px-4 py-10 text-center text-muted-foreground">No journal entries yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap gap-1" v-if="entries.links.length > 3">
                <template v-for="(link, i) in entries.links" :key="i">
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
