<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import MemberFormFields from './MemberFormFields.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Members', href: '/members' },
    { title: 'New Member', href: '/members/create' },
];

const form = useForm({
    type: 'individual',
    first_name: '',
    last_name: null as string | null,
    nin: null as string | null,
    date_of_birth: null as string | null,
    gender: null as string | null,
    phone: '',
    email: null as string | null,
    district: null as string | null,
    subcounty: null as string | null,
    village: null as string | null,
    occupation: null as string | null,
    photo: null as File | null,
    signature: null as File | null,
    next_of_kin: [{ name: '', relationship: '', phone: '', nin: null, address: null }],
});

const submit = () => {
    form.post('/members', { forceFormData: true });
};
</script>

<template>
    <Head title="New Member" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <form class="flex flex-col gap-6 p-4" @submit.prevent="submit">
            <h1 class="text-xl font-semibold">Register New Member</h1>

            <MemberFormFields :form="form as any" />

            <div class="flex gap-3">
                <Button type="submit" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    Register member
                </Button>
                <Button type="button" variant="outline" @click="$inertia.visit('/members')">Cancel</Button>
            </div>
        </form>
    </AppLayout>
</template>
