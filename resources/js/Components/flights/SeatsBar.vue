<script setup>
import { computed } from 'vue';

const props = defineProps({
    total: { type: Number, required: true },
    confirmed: { type: Number, default: 0 },
    pending: { type: Number, default: 0 },
    compact: { type: Boolean, default: false },
});

const pct = (n) => (props.total ? Math.min(100, (n / props.total) * 100) : 0);
const available = computed(() => Math.max(0, props.total - props.confirmed));
</script>

<template>
    <div :class="compact ? 'w-32' : 'w-full'">
        <div class="flex items-baseline justify-between text-xs">
            <span class="font-semibold text-slate-900">{{ available }} <span class="font-normal text-slate-500">left</span></span>
            <span class="text-slate-400">/ {{ total }}</span>
        </div>
        <div class="mt-1.5 flex h-1.5 overflow-hidden rounded-full bg-slate-100" role="img" :aria-label="`${confirmed} confirmed, ${pending} pending, ${available} available of ${total}`">
            <div class="bg-secondary" :style="{ width: pct(confirmed) + '%' }" />
            <div class="bg-tertiary" :style="{ width: pct(pending) + '%' }" />
        </div>
        <div v-if="!compact" class="mt-2 flex gap-4 text-[11px] text-slate-500">
            <span class="flex items-center gap-1"><span class="size-2 rounded-full bg-secondary" /> {{ confirmed }} confirmed</span>
            <span class="flex items-center gap-1"><span class="size-2 rounded-full bg-tertiary" /> {{ pending }} pending</span>
            <span class="flex items-center gap-1"><span class="size-2 rounded-full bg-slate-200" /> {{ available }} available</span>
        </div>
    </div>
</template>
