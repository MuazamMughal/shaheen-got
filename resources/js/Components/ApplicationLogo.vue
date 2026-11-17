<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

defineProps({
    // `mark` = square logo tile only; `full` = tile + wordmark
    variant: { type: String, default: 'full' },
    // Text color for the wordmark: `dark` on light backgrounds, `light` on navy.
    tone: { type: String, default: 'dark' },
});

const site = computed(() => usePage().props.settings?.site ?? {});
const failed = ref(false);
</script>

<template>
    <span class="inline-flex items-center gap-3">
        <span class="grid size-11 shrink-0 place-items-center overflow-hidden rounded-xl bg-secondary ring-1 ring-tertiary/40">
            <img
                v-if="!failed && site.logo" :src="site.logo" :alt="site.name"
                class="size-full object-cover" @error="failed = true"
            />
            <span v-else class="font-display text-lg font-bold text-tertiary">S</span>
        </span>
        <span v-if="variant === 'full'" class="leading-tight">
            <span :class="['block font-display text-base font-bold tracking-[0.18em]', tone === 'light' ? 'text-tertiary' : 'text-secondary']">SHAHEEN</span>
            <span :class="['block text-[10px] font-medium uppercase tracking-[0.22em]', tone === 'light' ? 'text-white/60' : 'text-slate-500']">Group of Travels</span>
        </span>
    </span>
</template>
