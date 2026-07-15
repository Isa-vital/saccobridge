<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import FlashMessages from '@/components/FlashMessages.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface SessionRow {
    id: number;
    teller: string;
    opening_float: string;
    closing_declared: string | null;
    closing_system: string | null;
    variance: string | null;
    status: string;
    opened_by: string;
    opened_at: string;
    closed_at: string | null;
    is_own: boolean;
}

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
}

defineProps<{
    sessions: Paginated<SessionRow>;
    tellers: { id: number; name: string }[];
    can: { manage: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Teller Sessions', href: '/savings/sessions' }];

const showOpen = ref(false);
const openForm = useForm({
    user_id: null as number | null,
    opening_float: '',
});

const submitOpen = () => {
    openForm.post('/savings/sessions', {
        preserveScroll: true,
        onSuccess: () => {
            openForm.reset();
            showOpen.value = false;
        },
    });
};

const closingId = ref<number | null>(null);
const closingAmount = ref('');

const submitClose = (id: number) => {
    router.post(`/savings/sessions/${id}/close`, { closing_declared: closingAmount.value }, {
        preserveScroll: true,
        onSuccess: () => {
            closingId.value = null;
            closingAmount.value = '';
        },
    });
};

const reconcile = (id: number) => {
    if (confirm(`Reconcile session #${id}? This confirms the variance has been reviewed.`)) {
        router.post(`/savings/sessions/${id}/reconcile`, {}, { preserveScroll: true });
    }
};

const ugx = (value: string | null) => (value === null ? '—' : new Intl.NumberFormat('en-UG').format(Number(value)));

const statusClasses: Record<string, string> = {
    open: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    closed: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    reconciled: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
};
</script>

<template>
    <Head title="Teller Sessions" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <FlashMessages />

            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-xl font-semibold">Teller Sessions</h1>
                <Button v-if="can.manage" @click="showOpen = !showOpen">{{ showOpen ? 'Cancel' : 'Issue Float / Open Session' }}</Button>
            </div>

            <form v-if="showOpen" class="grid items-end gap-3 rounded-xl border p-4 md:grid-cols-3" @submit.prevent="submitOpen">
                <div class="grid gap-1.5">
                    <Label for="teller">Teller</Label>
                    <select id="teller" v-model="openForm.user_id" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm" required>
                        <option :value="null" disabled>Select teller…</option>
                        <option v-for="teller in tellers" :key="teller.id" :value="teller.id">{{ teller.name }}</option>
                    </select>
                    <p v-if="openForm.errors.user_id" class="text-xs text-destructive">{{ openForm.errors.user_id }}</p>
                </div>
                <div class="grid gap-1.5">
                    <Label for="float">Opening float (UGX)</Label>
                    <Input id="float" v-model="openForm.opening_float" type="number" step="0.01" min="0.01" required />
                    <p v-if="openForm.errors.opening_float" class="text-xs text-destructive">{{ openForm.errors.opening_float }}</p>
                </div>
                <Button type="submit" :disabled="openForm.processing">Open session</Button>
            </form>

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">#</th>
                            <th class="px-4 py-3 font-medium">Teller</th>
                            <th class="px-4 py-3 text-right font-medium">Float</th>
                            <th class="px-4 py-3 text-right font-medium">Declared</th>
                            <th class="px-4 py-3 text-right font-medium">System</th>
                            <th class="px-4 py-3 text-right font-medium">Variance</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Opened</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="session in sessions.data" :key="session.id" class="border-b last:border-0 hover:bg-muted/50">
                            <td class="px-4 py-3 font-mono">{{ session.id }}</td>
                            <td class="px-4 py-3">{{ session.teller }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ ugx(session.opening_float) }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ ugx(session.closing_declared) }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ ugx(session.closing_system) }}</td>
                            <td class="px-4 py-3 text-right font-mono" :class="session.variance && Number(session.variance) !== 0 ? 'font-semibold text-red-600' : ''">
                                {{ ugx(session.variance) }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="statusClasses[session.status]">
                                    {{ session.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ session.opened_at }}</td>
                            <td class="px-4 py-3 text-right">
                                <template v-if="session.status === 'open' && (session.is_own || can.manage)">
                                    <div v-if="closingId === session.id" class="flex items-center justify-end gap-2">
                                        <Input v-model="closingAmount" type="number" step="0.01" min="0" placeholder="counted cash" class="w-36" />
                                        <Button size="sm" @click="submitClose(session.id)">Close</Button>
                                        <Button size="sm" variant="ghost" @click="closingId = null">✕</Button>
                                    </div>
                                    <Button v-else size="sm" variant="outline" @click="closingId = session.id">Close day</Button>
                                </template>
                                <Button
                                    v-else-if="session.status === 'closed' && can.manage && !session.is_own"
                                    size="sm"
                                    variant="outline"
                                    @click="reconcile(session.id)"
                                >
                                    Reconcile
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="sessions.data.length === 0">
                            <td colspan="9" class="px-4 py-10 text-center text-muted-foreground">No teller sessions yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap gap-1" v-if="sessions.links.length > 3">
                <template v-for="(link, i) in sessions.links" :key="i">
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
