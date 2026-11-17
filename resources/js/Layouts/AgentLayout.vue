<script setup>
import { computed, ref } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import {
    Bars3Icon, XMarkIcon, HomeIcon, Squares2X2Icon, UserGroupIcon, TicketIcon, UserCircleIcon,
} from '@heroicons/vue/24/outline';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import SidebarLink from '@/Components/SidebarLink.vue';
import UserMenu from '@/Components/UserMenu.vue';
import NotificationBell from '@/Components/NotificationBell.vue';
import FlashToaster from '@/Components/FlashToaster.vue';
import WhatsAppFab from '@/Components/WhatsAppFab.vue';

defineProps({
    title: { type: String, default: null },
    hideNav: { type: Boolean, default: false }, // e.g. the "pending approval" screen
});

const mobileOpen = ref(false);
const settings = computed(() => usePage().props.settings);

const nav = [
    { name: 'Home', route: 'home', icon: HomeIcon },
    { name: 'Dashboard', route: 'agent.dashboard', icon: Squares2X2Icon },
    { name: 'All Groups', route: 'agent.groups.index', icon: UserGroupIcon, match: 'agent.groups.*' },
    { name: 'My Bookings', route: 'agent.bookings.index', icon: TicketIcon, match: 'agent.bookings.*' },
    { name: 'My Profile', route: 'profile.edit', icon: UserCircleIcon },
];

const hasRoute = (item) => item.route && route().has(item.route);
const isActive = (item) => hasRoute(item) && route().current(item.match ?? item.route);
</script>

<template>
    <Head :title="title" />
    <FlashToaster />

    <div class="min-h-screen bg-primary">
        <Transition enter-active-class="transition-opacity duration-200" enter-from-class="opacity-0" leave-active-class="transition-opacity duration-150" leave-to-class="opacity-0">
            <div v-if="mobileOpen && !hideNav" class="fixed inset-0 z-40 bg-secondary-900/50 backdrop-blur-sm lg:hidden" @click="mobileOpen = false" />
        </Transition>

        <aside
            v-if="!hideNav"
            :class="['fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-slate-200/70 bg-white transition-transform duration-200 lg:translate-x-0',
                mobileOpen ? 'translate-x-0' : '-translate-x-full']"
        >
            <div class="flex h-20 items-center justify-between border-b border-slate-100 px-5">
                <ApplicationLogo />
                <button class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 lg:hidden" aria-label="Close menu" @click="mobileOpen = false">
                    <XMarkIcon class="size-6" />
                </button>
            </div>
            <nav class="flex-1 space-y-1 overflow-y-auto p-4">
                <SidebarLink
                    v-for="item in nav" :key="item.name" tone="light"
                    :href="hasRoute(item) ? route(item.route) : '#'" :active="isActive(item)" :icon="item.icon"
                    :disabled="!hasRoute(item)"
                >{{ item.name }}</SidebarLink>
            </nav>
            <div class="m-4 overflow-hidden rounded-2xl bg-secondary p-4 text-white">
                <p class="font-display text-sm font-semibold text-tertiary">Need help?</p>
                <p class="mt-1 text-xs text-white/70">Our team is available on WhatsApp for bookings and payments.</p>
            </div>
        </aside>

        <div :class="hideNav ? '' : 'lg:pl-72'">
            <header class="sticky top-0 z-30 border-b border-slate-200/70 bg-white/85 backdrop-blur">
                <div class="flex h-20 items-center gap-4 px-4 sm:px-6 lg:px-8">
                    <button v-if="!hideNav" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden" aria-label="Open menu" @click="mobileOpen = true">
                        <Bars3Icon class="size-6" />
                    </button>
                    <ApplicationLogo v-if="hideNav" />
                    <h1 v-else-if="title" class="min-w-0 flex-1 truncate text-lg font-semibold sm:text-xl">{{ title }}</h1>
                    <div class="flex-1" />
                    <NotificationBell />
                <UserMenu />
                </div>
                <!-- Announcement ticker (Admin → Settings) -->
                <div v-if="settings?.site?.ticker && !hideNav" class="overflow-hidden bg-gradient-to-r from-secondary via-secondary-700 to-secondary py-2">
                    <p class="ticker whitespace-nowrap text-sm font-medium text-white">
                        <span class="text-tertiary">✦</span> {{ settings.site.ticker }}
                    </p>
                </div>
            </header>

            <main class="px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                <slot />
            </main>
        </div>

        <WhatsAppFab />
    </div>
</template>

<style scoped>
.ticker {
    display: inline-block;
    padding-left: 100%;
    animation: ticker 28s linear infinite;
}
@keyframes ticker {
    to { transform: translateX(-100%); }
}
@media (prefers-reduced-motion: reduce) {
    .ticker { animation: none; padding-left: 1rem; }
}
</style>
