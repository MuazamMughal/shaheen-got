<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    tone: { type: String, default: 'navy' }, // navy | gold | green | red | amber
    hint: { type: String, default: null },
    href: { type: String, default: null },
});

const tones = {
    navy: { icon: 'bg-secondary-50 text-secondary', bar: 'from-secondary to-secondary-400' },
    gold: { icon: 'bg-tertiary-50 text-tertiary-700', bar: 'from-tertiary to-tertiary-300' },
    green: { icon: 'bg-emerald-50 text-emerald-600', bar: 'from-emerald-500 to-emerald-300' },
    red: { icon: 'bg-rose-50 text-rose-600', bar: 'from-rose-500 to-rose-300' },
    amber: { icon: 'bg-amber-50 text-amber-600', bar: 'from-amber-500 to-amber-300' },
};
</script>

<template>
    <component
        :is="href ? Link : 'div'"
        :href="href"
        class="card group relative overflow-hidden p-5 transition hover:-translate-y-0.5 hover:shadow-lg"
    >
        <div :class="['absolute inset-x-0 top-0 h-1 bg-gradient-to-r', (tones[tone] ?? tones.navy).bar]" />
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ label }}</p>
                <p class="mt-2 font-display text-3xl font-semibold text-slate-900">{{ value }}</p>
                <p v-if="hint" class="mt-1 text-xs text-slate-500">{{ hint }}</p>
            </div>
            <div v-if="$slots.icon" :class="['grid size-11 place-items-center rounded-xl', (tones[tone] ?? tones.navy).icon]">
                <slot name="icon" />
            </div>
        </div>
    </component>
</template>
