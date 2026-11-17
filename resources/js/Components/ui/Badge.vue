<script setup>
import { computed } from 'vue';

const props = defineProps({
    color: { type: String, default: 'slate' }, // slate | navy | gold | green | red | amber | blue
    dot: { type: Boolean, default: false },
});

const colors = {
    slate: 'bg-slate-100 text-slate-700 ring-slate-200',
    navy: 'bg-secondary-50 text-secondary ring-secondary-100',
    gold: 'bg-tertiary-50 text-tertiary-700 ring-tertiary-200',
    green: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    red: 'bg-rose-50 text-rose-700 ring-rose-200',
    amber: 'bg-amber-50 text-amber-700 ring-amber-200',
    blue: 'bg-sky-50 text-sky-700 ring-sky-200',
};

const dots = {
    slate: 'bg-slate-400', navy: 'bg-secondary', gold: 'bg-tertiary', green: 'bg-emerald-500',
    red: 'bg-rose-500', amber: 'bg-amber-500', blue: 'bg-sky-500',
};

const classes = computed(() => colors[props.color] ?? colors.slate);
</script>

<template>
    <span :class="['inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset', classes]">
        <span v-if="dot" :class="['size-1.5 rounded-full', dots[color] ?? dots.slate]" />
        <slot />
    </span>
</template>
