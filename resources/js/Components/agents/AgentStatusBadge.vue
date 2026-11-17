<script setup>
import Badge from '@/Components/ui/Badge.vue';

defineProps({ status: { type: String, required: true }, label: { type: String, default: null } });
const colors = { pending: 'amber', approved: 'green', rejected: 'red', suspended: 'slate' };
</script>

<template>
    <Badge :color="colors[status] ?? 'slate'" dot>{{ label ?? status }}</Badge>
</template>
