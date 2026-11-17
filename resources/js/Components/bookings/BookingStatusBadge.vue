<script setup>
import Badge from '@/Components/ui/Badge.vue';

defineProps({ booking: { type: Object, required: true }, withPayment: { type: Boolean, default: false } });
</script>

<template>
    <div class="flex flex-wrap items-center gap-1.5">
        <Badge :color="booking.status_color" dot>{{ booking.status_label }}</Badge>
        <Badge v-if="withPayment" :color="booking.payment_status === 'approved' ? 'green' : 'slate'">
            {{ booking.payment_status === 'approved' ? 'Paid' : 'Unpaid' }}
        </Badge>
    </div>
</template>
