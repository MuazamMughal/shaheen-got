<script setup>
import { Link } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import FlashToaster from '@/Components/FlashToaster.vue';

defineProps({
    title: { type: String, default: null },
    subtitle: { type: String, default: null },
    wide: { type: Boolean, default: false },
});
</script>

<template>
    <FlashToaster />
    <div class="grid min-h-screen bg-primary lg:grid-cols-[1.05fr_1fr]">
        <!-- Brand panel -->
        <aside class="relative hidden overflow-hidden bg-secondary lg:flex lg:flex-col lg:justify-between lg:p-12">
            <div class="pointer-events-none absolute -right-40 -top-40 size-[34rem] rounded-full border border-tertiary/15" />
            <div class="pointer-events-none absolute -right-24 -top-24 size-[24rem] rounded-full border border-tertiary/20" />
            <div class="pointer-events-none absolute -bottom-32 -left-32 size-[28rem] rounded-full bg-tertiary/10 blur-3xl" />

            <Link :href="route('home')" class="relative"><ApplicationLogo tone="light" /></Link>

            <div class="relative max-w-md">
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-tertiary">B2B Group Ticketing</p>
                <h2 class="mt-4 font-display text-4xl font-semibold leading-tight !text-white">
                    Umrah & group fares,<br />booked in minutes.
                </h2>
                <div class="gold-rule mt-6" />
                <p class="mt-6 text-sm leading-relaxed text-white/70">
                    Live seat availability across Umrah, KSA, UAE and more — with instant booking
                    confirmations for registered travel agents.
                </p>
            </div>

            <p class="relative text-xs text-white/40">© {{ new Date().getFullYear() }} Shaheen Group of Travels</p>
        </aside>

        <!-- Form panel -->
        <main class="flex flex-col items-center justify-center px-4 py-10 sm:px-8">
            <Link :href="route('home')" class="mb-8 lg:hidden"><ApplicationLogo /></Link>
            <div :class="['w-full', wide ? 'max-w-2xl' : 'max-w-md']">
                <div v-if="title" class="mb-8">
                    <h1 class="font-display text-2xl font-semibold sm:text-3xl">{{ title }}</h1>
                    <p v-if="subtitle" class="mt-2 text-sm text-slate-500">{{ subtitle }}</p>
                </div>
                <slot />
            </div>
        </main>
    </div>
</template>
