<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

interface MemberRow {
    id: number;
    member_no: string;
    full_name: string;
    type: string;
    phone: string;
    nin: string | null;
    status: string;
    joined_at: string | null;
}

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
}

const props = defineProps<{
    members: Paginated<MemberRow>;
    filters: { search?: string; status?: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Members', href: '/members' }];

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');

let debounce: ReturnType<typeof setTimeout>;
watch([search, status], () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get('/members', { search: search.value || undefined, status: status.value || undefined }, { preserveState: true, replace: true });
    }, 300);
});

const statusClasses: Record<string, string> = {
    pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    active: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    dormant: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
    exited: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
};
</script>

<template>
    <Head title="Members" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-xl font-semibold">Member Register</h1>
                <Link href="/members/create">
                    <Button>New Member</Button>
                </Link>
            </div>

            <div class="flex flex-wrap gap-3">
                <Input v-model="search" placeholder="Search name, member no, NIN or phone…" class="max-w-sm" />
                <select
                    v-model="status"
                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-1"
                >
                    <option value="">All statuses</option>
                    <option value="pending">Pending</option>
                    <option value="active">Active</option>
                    <option value="dormant">Dormant</option>
                    <option value="exited">Exited</option>
                </select>
            </div>

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">Member No</th>
                            <th class="px-4 py-3 font-medium">Name</th>
                            <th class="px-4 py-3 font-medium">Type</th>
                            <th class="px-4 py-3 font-medium">Phone</th>
                            <th class="px-4 py-3 font-medium">NIN</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="member in members.data"
                            :key="member.id"
                            class="cursor-pointer border-b transition-colors last:border-0 hover:bg-muted/50"
                            @click="router.visit(`/members/${member.id}`)"
                        >
                            <td class="px-4 py-3 font-mono">{{ member.member_no }}</td>
                            <td class="px-4 py-3">{{ member.full_name }}</td>
                            <td class="px-4 py-3 capitalize">{{ member.type }}</td>
                            <td class="px-4 py-3">{{ member.phone }}</td>
                            <td class="px-4 py-3">{{ member.nin ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="statusClasses[member.status]">
                                    {{ member.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3">{{ member.joined_at ?? '—' }}</td>
                        </tr>
                        <tr v-if="members.data.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-muted-foreground">No members found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap gap-1" v-if="members.links.length > 3">
                <template v-for="(link, i) in members.links" :key="i">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded-md px-3 py-1.5 text-sm"
                        :class="link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
                        v-html="link.label"
                        preserve-state
                    />
                    <span v-else class="px-3 py-1.5 text-sm text-muted-foreground" v-html="link.label" />
                </template>
            </div>
        </div>
    </AppLayout>
</template>
