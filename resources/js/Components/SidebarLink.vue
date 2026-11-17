<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: { type: String, required: true },
    active: { type: Boolean, default: false },
    icon: { type: [Object, Function], default: null },
    tone: { type: String, default: 'dark' }, // dark = navy sidebar, light = white sidebar
    disabled: { type: Boolean, default: false },
    badge: { type: String, default: null },
});
</script>

<template>
    <Link
        v-if="!disabled" :href="href"
        :aria-current="active ? 'page' : undefined"
        :class="[
            'group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
            tone === 'dark'
                ? (active ? 'bg-white/10 text-white' : 'text-white/65 hover:bg-white/5 hover:text-white')
                : (active ? 'bg-secondary text-white shadow-md shadow-secondary/25' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'),
        ]"
    >
        <span v-if="active && tone === 'dark'" class="absolute inset-y-2 left-0 w-1 rounded-r-full bg-tertiary" />
        <component :is="icon" v-if="icon" :class="['size-5 shrink-0', active ? 'text-tertiary' : tone === 'dark' ? 'text-white/50 group-hover:text-tertiary' : 'text-slate-400 group-hover:text-secondary']" />
        <span class="truncate"><slot /></span>
    </Link>
    <span
        v-else
        :class="['flex cursor-not-allowed items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium', tone === 'dark' ? 'text-white/30' : 'text-slate-300']"
        :title="'Not available'"
    >
        <component :is="icon" v-if="icon" class="size-5 shrink-0" />
        <span class="flex-1 truncate"><slot /></span>
        <span v-if="badge" :class="['rounded-md px-1.5 py-0.5 text-[10px] font-semibold uppercase', tone === 'dark' ? 'bg-white/5 text-white/40' : 'bg-slate-100 text-slate-400']">{{ badge }}</span>
    </span>
</template>
