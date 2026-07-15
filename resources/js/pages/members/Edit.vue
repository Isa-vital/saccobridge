<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import MemberFormFields from './MemberFormFields.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

interface KinRow {
    name: string;
    relationship: string;
    phone: string;
    nin: string | null;
    address: string | null;
}

const props = defineProps<{
    member: {
        id: number;
        member_no: string;
        type: string;
        first_name: string;
        last_name: string | null;
        nin: string | null;
        date_of_birth: string | null;
        gender: string | null;
        phone: string;
        email: string | null;
        district: string | null;
        subcounty: string | null;
        village: string | null;
        occupation: string | null;
        next_of_kin: KinRow[];
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Members', href: '/members' },
    { title: props.member.member_no, href: `/members/${props.member.id}` },
    { title: 'Edit', href: `/members/${props.member.id}/edit` },
];

const form = useForm({
    type: props.member.type,
    first_name: props.member.first_name,
    last_name: props.member.last_name,
    nin: props.member.nin,
    date_of_birth: props.member.date_of_birth?.substring(0, 10) ?? null,
    gender: props.member.gender,
    phone: props.member.phone,
    email: props.member.email,
    district: props.member.district,
    subcounty: props.member.subcounty,
    village: props.member.village,
    occupation: props.member.occupation,
    photo: null as File | null,
    signature: null as File | null,
    next_of_kin: props.member.next_of_kin.map((kin) => ({
        name: kin.name,
        relationship: kin.relationship,
        phone: kin.phone,
        nin: kin.nin,
        address: kin.address,
    })),
});

const submit = () => {
    // Inertia file uploads require POST + method spoofing
    form.transform((data) => ({ ...data, _method: 'put' }))
        .post(`/members/${props.member.id}`, { forceFormData: true });
};
</script>

<template>
    <Head :title="`Edit ${member.member_no}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <form class="flex flex-col gap-6 p-4" @submit.prevent="submit">
            <h1 class="text-xl font-semibold">Edit Member — {{ member.member_no }}</h1>

            <MemberFormFields :form="form as any" :show-type="false" />

            <div class="flex gap-3">
                <Button type="submit" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    Save changes
                </Button>
                <Button type="button" variant="outline" @click="$inertia.visit(`/members/${member.id}`)">Cancel</Button>
            </div>
        </form>
    </AppLayout>
</template>
