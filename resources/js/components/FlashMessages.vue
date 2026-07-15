<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const flash = computed(() => (page.props as any).flash ?? {});
</script>

<template>
    <div v-if="flash.success" class="rounded-md bg-green-100 px-4 py-3 text-sm text-green-800 dark:bg-green-900 dark:text-green-200">
        {{ flash.success }}
    </div>
    <div v-if="flash.error" class="rounded-md bg-red-100 px-4 py-3 text-sm text-red-800 dark:bg-red-900 dark:text-red-200">
        {{ flash.error }}
    </div>
</template>
