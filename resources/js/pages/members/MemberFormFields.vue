<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';

export interface KinRow {
    name: string;
    relationship: string;
    phone: string;
    nin: string | null;
    address: string | null;
}

export interface MemberForm {
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
    photo: File | null;
    signature: File | null;
    next_of_kin: KinRow[];
    [key: string]: unknown;
}

defineProps<{
    form: MemberForm & { errors: Record<string, string> };
    showType?: boolean;
}>();

const emptyKin = (): KinRow => ({ name: '', relationship: '', phone: '', nin: null, address: null });

defineExpose({ emptyKin });
</script>

<template>
    <div class="grid gap-6">
        <!-- Identity -->
        <div class="grid gap-4 md:grid-cols-3">
            <div v-if="showType !== false" class="grid gap-2">
                <Label for="type">Member type</Label>
                <select
                    id="type"
                    v-model="form.type"
                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                >
                    <option value="individual">Individual</option>
                    <option value="group">Group</option>
                    <option value="institution">Institution</option>
                </select>
                <InputError :message="form.errors.type" />
            </div>

            <div class="grid gap-2">
                <Label for="first_name">{{ form.type === 'individual' ? 'First name' : 'Name' }}</Label>
                <Input id="first_name" v-model="form.first_name" required />
                <InputError :message="form.errors.first_name" />
            </div>

            <div v-if="form.type === 'individual'" class="grid gap-2">
                <Label for="last_name">Last name</Label>
                <Input id="last_name" v-model="form.last_name" />
                <InputError :message="form.errors.last_name" />
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="grid gap-2">
                <Label for="nin">NIN (National ID)</Label>
                <Input id="nin" v-model="form.nin" placeholder="CMxxxxxxxxxxxx" />
                <InputError :message="form.errors.nin" />
            </div>
            <div v-if="form.type === 'individual'" class="grid gap-2">
                <Label for="date_of_birth">Date of birth</Label>
                <Input id="date_of_birth" v-model="form.date_of_birth" type="date" />
                <InputError :message="form.errors.date_of_birth" />
            </div>
            <div v-if="form.type === 'individual'" class="grid gap-2">
                <Label for="gender">Gender</Label>
                <select
                    id="gender"
                    v-model="form.gender"
                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                >
                    <option :value="null">—</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                    <option value="other">Other</option>
                </select>
                <InputError :message="form.errors.gender" />
            </div>
        </div>

        <!-- Contact -->
        <div class="grid gap-4 md:grid-cols-3">
            <div class="grid gap-2">
                <Label for="phone">Phone</Label>
                <Input id="phone" v-model="form.phone" placeholder="+2567XXXXXXXX" required />
                <InputError :message="form.errors.phone" />
            </div>
            <div class="grid gap-2">
                <Label for="email">Email</Label>
                <Input id="email" v-model="form.email" type="email" />
                <InputError :message="form.errors.email" />
            </div>
            <div class="grid gap-2">
                <Label for="occupation">Occupation</Label>
                <Input id="occupation" v-model="form.occupation" />
                <InputError :message="form.errors.occupation" />
            </div>
        </div>

        <!-- Address -->
        <div class="grid gap-4 md:grid-cols-3">
            <div class="grid gap-2">
                <Label for="district">District</Label>
                <Input id="district" v-model="form.district" />
                <InputError :message="form.errors.district" />
            </div>
            <div class="grid gap-2">
                <Label for="subcounty">Subcounty</Label>
                <Input id="subcounty" v-model="form.subcounty" />
                <InputError :message="form.errors.subcounty" />
            </div>
            <div class="grid gap-2">
                <Label for="village">Village</Label>
                <Input id="village" v-model="form.village" />
                <InputError :message="form.errors.village" />
            </div>
        </div>

        <!-- KYC uploads -->
        <div class="grid gap-4 md:grid-cols-2">
            <div class="grid gap-2">
                <Label for="photo">Passport photo</Label>
                <input
                    id="photo"
                    type="file"
                    accept="image/*"
                    class="text-sm"
                    @change="form.photo = ($event.target as HTMLInputElement).files?.[0] ?? null"
                />
                <InputError :message="form.errors.photo" />
            </div>
            <div class="grid gap-2">
                <Label for="signature">Signature specimen</Label>
                <input
                    id="signature"
                    type="file"
                    accept="image/*"
                    class="text-sm"
                    @change="form.signature = ($event.target as HTMLInputElement).files?.[0] ?? null"
                />
                <InputError :message="form.errors.signature" />
            </div>
        </div>

        <!-- Next of kin -->
        <div class="grid gap-3">
            <div class="flex items-center justify-between">
                <Label>Next of kin</Label>
                <Button type="button" variant="outline" size="sm" @click="form.next_of_kin.push(emptyKin())">
                    Add next of kin
                </Button>
            </div>
            <div
                v-for="(kin, i) in form.next_of_kin"
                :key="i"
                class="grid items-end gap-3 rounded-lg border p-3 md:grid-cols-[1fr_1fr_1fr_1fr_auto]"
            >
                <div class="grid gap-1.5">
                    <Label :for="`kin_name_${i}`" class="text-xs">Name</Label>
                    <Input :id="`kin_name_${i}`" v-model="kin.name" required />
                    <InputError :message="form.errors[`next_of_kin.${i}.name`]" />
                </div>
                <div class="grid gap-1.5">
                    <Label :for="`kin_rel_${i}`" class="text-xs">Relationship</Label>
                    <Input :id="`kin_rel_${i}`" v-model="kin.relationship" placeholder="Spouse, Parent…" required />
                    <InputError :message="form.errors[`next_of_kin.${i}.relationship`]" />
                </div>
                <div class="grid gap-1.5">
                    <Label :for="`kin_phone_${i}`" class="text-xs">Phone</Label>
                    <Input :id="`kin_phone_${i}`" v-model="kin.phone" required />
                    <InputError :message="form.errors[`next_of_kin.${i}.phone`]" />
                </div>
                <div class="grid gap-1.5">
                    <Label :for="`kin_nin_${i}`" class="text-xs">NIN (optional)</Label>
                    <Input :id="`kin_nin_${i}`" v-model="kin.nin" />
                </div>
                <Button type="button" variant="ghost" size="sm" class="text-destructive" @click="form.next_of_kin.splice(i, 1)">
                    Remove
                </Button>
            </div>
        </div>
    </div>
</template>
