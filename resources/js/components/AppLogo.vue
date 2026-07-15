<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Props {
    class?: string;
}

defineProps<Props>();

const page = usePage();
const saccoName = computed(() => (page.props as any).tenant?.name ?? 'SaccoBridge');
</script>

<template>
    <div class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
        <AppLogoIcon class="size-5 fill-current text-white dark:text-black" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <!-- CHANGED: show the SACCO (tenant) name instead of starter kit branding -->
        <span class="mb-0.5 truncate font-semibold leading-none">{{ saccoName }}</span>
        <span class="truncate text-xs text-muted-foreground">SaccoBridge</span>
    </div>
</template>
