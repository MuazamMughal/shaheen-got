<script setup>
/** Category tile in the style of the agent-facing cards (image + navy corner + plane). */
import { PaperAirplaneIcon } from '@heroicons/vue/24/solid';

defineProps({
    category: { type: Object, required: true },
    muted: { type: Boolean, default: false },
});
</script>

<template>
    <div :class="['card group flex h-full flex-col overflow-hidden transition', muted ? 'opacity-60 grayscale' : 'hover:-translate-y-0.5 hover:shadow-xl']">
        <div class="relative aspect-[16/10] overflow-hidden bg-gradient-to-br from-secondary via-secondary-700 to-secondary-900">
            <img v-if="category.image_url" :src="category.image_url" :alt="category.name" class="absolute inset-0 size-full object-cover transition duration-500 group-hover:scale-105" />
            <template v-else>
                <div class="absolute -right-10 -top-10 size-40 rounded-full border border-tertiary/25" />
                <div class="absolute -right-4 -top-4 size-24 rounded-full border border-tertiary/30" />
            </template>
            <!-- Navy curved corner with plane, as on the reference cards -->
            <svg class="absolute -bottom-px right-0 h-14 w-3/5 text-secondary" viewBox="0 0 200 50" preserveAspectRatio="none" aria-hidden="true">
                <path d="M0 50 C 70 46, 140 30, 200 0 L 200 50 Z" fill="currentColor" />
            </svg>
            <PaperAirplaneIcon class="absolute bottom-2.5 right-4 size-5 -rotate-45 text-white" />
            <slot name="badge" />
        </div>
        <div class="flex flex-1 flex-col p-5">
            <h3 class="font-display text-lg font-semibold leading-snug">{{ category.name }}</h3>
            <div v-if="category.country" class="mt-2 flex items-center gap-2.5 text-sm text-slate-500">
                <img v-if="category.flag_url" :src="category.flag_url" alt="" class="size-6 rounded-full object-cover ring-1 ring-slate-200" />
                <span v-if="category.flag_url" class="h-4 w-px bg-slate-200" />
                <span>{{ category.country }}</span>
            </div>
            <slot />
        </div>
    </div>
</template>
