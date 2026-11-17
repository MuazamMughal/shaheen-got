<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    variant: { type: String, default: 'primary' }, // primary | gold | secondary | ghost | danger | success
    size: { type: String, default: 'md' },         // sm | md | lg
    type: { type: String, default: 'button' },
    href: { type: String, default: null },
    loading: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    block: { type: Boolean, default: false },
    as: { type: String, default: null }, // 'a' = plain link (downloads, external)
});

const variants = {
    primary: 'bg-secondary text-white hover:bg-secondary-700 focus-visible:ring-secondary-300 shadow-sm shadow-secondary/20',
    gold: 'bg-tertiary text-secondary-900 hover:bg-tertiary-600 hover:text-white focus-visible:ring-tertiary-300 shadow-sm shadow-tertiary/30',
    secondary: 'bg-white text-slate-700 ring-1 ring-inset ring-slate-200 hover:bg-slate-50 hover:ring-slate-300 focus-visible:ring-secondary-200',
    ghost: 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 focus-visible:ring-slate-200',
    danger: 'bg-rose-600 text-white hover:bg-rose-700 focus-visible:ring-rose-300',
    success: 'bg-emerald-600 text-white hover:bg-emerald-700 focus-visible:ring-emerald-300',
};

const sizes = {
    sm: 'h-8 px-3 text-xs gap-1.5',
    md: 'h-10 px-4 text-sm gap-2',
    lg: 'h-12 px-6 text-base gap-2',
};

const classes = computed(() => [
    'inline-flex items-center justify-center rounded-xl font-semibold transition duration-150',
    'focus:outline-none focus-visible:ring-4 disabled:cursor-not-allowed disabled:opacity-60',
    variants[props.variant] ?? variants.primary,
    sizes[props.size] ?? sizes.md,
    props.block ? 'w-full' : '',
]);
</script>

<template>
    <a v-if="href && !disabled && as === 'a'" :href="href" :class="classes">
        <slot />
    </a>
    <Link v-else-if="href && !disabled" :href="href" :class="classes">
        <slot />
    </Link>
    <button v-else :type="type" :class="classes" :disabled="disabled || loading">
        <svg v-if="loading" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25" />
            <path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
        </svg>
        <slot />
    </button>
</template>
