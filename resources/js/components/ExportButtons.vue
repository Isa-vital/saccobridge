<script setup lang="ts">
import { Button } from '@/components/ui/button';

defineProps<{ report: string; query?: Record<string, string> }>();

const url = (report: string, format: string, query?: Record<string, string>) => {
    const params = new URLSearchParams(query ?? {}).toString();
    return `/reports/${report}/export/${format}${params ? '?' + params : ''}`;
};
</script>

<template>
    <div class="flex gap-2">
        <a :href="url(report, 'pdf', query)">
            <Button variant="outline" size="sm">Export PDF</Button>
        </a>
        <a :href="url(report, 'csv', query)">
            <Button variant="outline" size="sm">Export CSV</Button>
        </a>
    </div>
</template>
