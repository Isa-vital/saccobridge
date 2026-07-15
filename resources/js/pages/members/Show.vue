<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface KinRow {
    id: number;
    name: string;
    relationship: string;
    phone: string;
    nin: string | null;
    address: string | null;
}

interface DocumentRow {
    id: number;
    type: string;
    original_name: string;
    uploaded_by: string | null;
    created_at: string;
}

const props = defineProps<{
    member: {
        id: number;
        member_no: string;
        type: string;
        full_name: string;
        nin: string | null;
        date_of_birth: string | null;
        gender: string | null;
        phone: string;
        email: string | null;
        district: string | null;
        subcounty: string | null;
        village: string | null;
        occupation: string | null;
        status: string;
        joined_at: string | null;
        approved_at: string | null;
        approver: string | null;
        creator: string | null;
        created_by: number | null;
        next_of_kin: KinRow[];
        documents: DocumentRow[];
    };
    can: { update: boolean; approve: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Members', href: '/members' },
    { title: props.member.member_no, href: `/members/${props.member.id}` },
];

const page = usePage();
const flash = computed(() => (page.props as any).flash ?? {});

const isOwnRegistration = computed(
    () => props.member.created_by !== null && props.member.created_by === (page.props as any).auth?.user?.id,
);

const approve = () => {
    if (confirm(`Approve and activate member ${props.member.member_no}?`)) {
        router.post(`/members/${props.member.id}/approve`);
    }
};

const statusClasses: Record<string, string> = {
    pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    active: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    dormant: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
    exited: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
};

const detail = (label: string, value: string | null) => ({ label, value: value ?? '—' });

const details = computed(() => [
    detail('Member No', props.member.member_no),
    detail('Type', props.member.type),
    detail('NIN', props.member.nin),
    detail('Date of birth', props.member.date_of_birth),
    detail('Gender', props.member.gender),
    detail('Phone', props.member.phone),
    detail('Email', props.member.email),
    detail('District', props.member.district),
    detail('Subcounty', props.member.subcounty),
    detail('Village', props.member.village),
    detail('Occupation', props.member.occupation),
    detail('Joined', props.member.joined_at),
    detail('Registered by', props.member.creator),
    detail('Approved by', props.member.approver),
]);
</script>

<template>
    <Head :title="member.member_no" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <div v-if="flash.success" class="rounded-md bg-green-100 px-4 py-3 text-sm text-green-800 dark:bg-green-900 dark:text-green-200">
                {{ flash.success }}
            </div>
            <div v-if="flash.error" class="rounded-md bg-red-100 px-4 py-3 text-sm text-red-800 dark:bg-red-900 dark:text-red-200">
                {{ flash.error }}
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <h1 class="text-xl font-semibold">{{ member.full_name }}</h1>
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium capitalize" :class="statusClasses[member.status]">
                        {{ member.status }}
                    </span>
                </div>
                <div class="flex gap-2">
                    <template v-if="member.status === 'pending' && can.approve">
                        <Button v-if="!isOwnRegistration" @click="approve">Approve &amp; Activate</Button>
                        <span v-else class="self-center text-sm text-muted-foreground">
                            Awaiting approval by another officer (maker-checker)
                        </span>
                    </template>
                    <Link v-if="can.update" :href="`/members/${member.id}/edit`">
                        <Button variant="outline">Edit</Button>
                    </Link>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Bio -->
                <div class="rounded-xl border border-sidebar-border/70 p-4 lg:col-span-2 dark:border-sidebar-border">
                    <h2 class="mb-4 font-medium">Member Details</h2>
                    <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                        <div v-for="d in details" :key="d.label" class="flex justify-between gap-4 border-b pb-2 text-sm last:border-0">
                            <dt class="text-muted-foreground">{{ d.label }}</dt>
                            <dd class="text-right font-medium capitalize">{{ d.value }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="flex flex-col gap-6">
                    <!-- Next of kin -->
                    <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <h2 class="mb-3 font-medium">Next of Kin</h2>
                        <div v-if="member.next_of_kin.length === 0" class="text-sm text-muted-foreground">None recorded.</div>
                        <div v-for="kin in member.next_of_kin" :key="kin.id" class="border-b py-2 text-sm last:border-0">
                            <div class="font-medium">{{ kin.name }}</div>
                            <div class="text-muted-foreground">{{ kin.relationship }} · {{ kin.phone }}</div>
                        </div>
                    </div>

                    <!-- KYC documents -->
                    <div class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <h2 class="mb-3 font-medium">KYC Documents</h2>
                        <div v-if="member.documents.length === 0" class="text-sm text-muted-foreground">No documents uploaded.</div>
                        <div v-for="doc in member.documents" :key="doc.id" class="border-b py-2 text-sm last:border-0">
                            <div class="font-medium capitalize">{{ doc.type.replace('_', ' ') }}</div>
                            <div class="text-muted-foreground">{{ doc.original_name }} · {{ doc.created_at }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
