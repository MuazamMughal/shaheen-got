<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import {
    Bars3Icon, XMarkIcon, Squares2X2Icon, PaperAirplaneIcon, TicketIcon, UsersIcon,
    RectangleGroupIcon, MapIcon, BuildingOffice2Icon, BriefcaseIcon, ShieldCheckIcon,
    Cog6ToothIcon, ClipboardDocumentListIcon, UserGroupIcon,
} from '@heroicons/vue/24/outline';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import SidebarLink from '@/Components/SidebarLink.vue';
import UserMenu from '@/Components/UserMenu.vue';
import NotificationBell from '@/Components/NotificationBell.vue';
import FlashToaster from '@/Components/FlashToaster.vue';
import { usePermissions } from '@/Composables/usePermissions';

defineProps({
    title: { type: String, default: null },
    subtitle: { type: String, default: null },
});

const mobileOpen = ref(false);
const { can } = usePermissions();

// Items without a route yet are shown disabled until their build step lands.
const sections = [
    { label: null, items: [
        { name: 'Dashboard', route: 'admin.dashboard', icon: Squares2X2Icon },
    ] },
    { label: 'Operations', items: [
        { name: 'Flights', route: 'admin.flights.index', icon: PaperAirplaneIcon, permission: 'flights.view' },
        { name: 'Bookings', route: 'admin.bookings.index', icon: TicketIcon, permission: 'bookings.view' },
        { name: 'Agents', route: 'admin.agents.index', icon: UserGroupIcon, permission: 'agents.view' },
    ] },
    { label: 'Masters', items: [
        { name: 'Categories', route: 'admin.categories.index', icon: RectangleGroupIcon, permission: 'categories.manage' },
        { name: 'Sectors', route: 'admin.sectors.index', icon: MapIcon, permission: 'sectors.manage' },
        { name: 'Airlines', route: 'admin.airlines.index', icon: BuildingOffice2Icon, permission: 'airlines.manage' },
        { name: 'Baggage Types', route: 'admin.baggage_types.index', icon: BriefcaseIcon, permission: 'baggage.manage' },
    ] },
    { label: 'Administration', items: [
        { name: 'Staff', route: 'admin.staff.index', icon: UsersIcon, permission: 'staff.manage' },
        { name: 'Roles & Permissions', route: 'admin.roles.index', icon: ShieldCheckIcon, permission: 'roles.manage' },
        { name: 'Activity Log', route: 'admin.activity.index', icon: ClipboardDocumentListIcon, permission: 'reports.view' },
        { name: 'Settings', route: 'admin.settings.edit', icon: Cog6ToothIcon, permission: 'settings.manage' },
    ] },
];

// Hide items (and empty sections) the user has no permission for.
const visibleSections = computed(() =>
    sections
        .map((s) => ({ ...s, items: s.items.filter((i) => can(i.permission)) }))
        .filter((s) => s.items.length),
);

const hasRoute = (item) => item.route && route().has(item.route);
// 'admin.staff.index' stays active on admin.staff.create / edit too.
const isActive = (item) => hasRoute(item) && route().current(item.route.replace(/\.(index|edit)$/, '') + '*');
const hrefFor = (item) => (hasRoute(item) ? route(item.route) : '#');
</script>

<template>
    <Head :title="title" />
    <FlashToaster />

    <div class="min-h-screen bg-primary">
        <!-- Mobile backdrop -->
        <Transition enter-active-class="transition-opacity duration-200" enter-from-class="opacity-0" leave-active-class="transition-opacity duration-150" leave-to-class="opacity-0">
            <div v-if="mobileOpen" class="fixed inset-0 z-40 bg-secondary-900/60 backdrop-blur-sm lg:hidden" @click="mobileOpen = false" />
        </Transition>

        <!-- Sidebar -->
        <aside
            :class="['fixed inset-y-0 left-0 z-50 flex w-72 flex-col bg-secondary transition-transform duration-200 lg:translate-x-0',
                mobileOpen ? 'translate-x-0' : '-translate-x-full']"
        >
            <div class="relative flex h-20 items-center justify-between border-b border-white/10 px-5">
                <ApplicationLogo tone="light" />
                <button class="rounded-lg p-1.5 text-white/60 hover:bg-white/10 lg:hidden" aria-label="Close menu" @click="mobileOpen = false">
                    <XMarkIcon class="size-6" />
                </button>
            </div>

            <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5">
                <div v-for="(section, i) in visibleSections" :key="i">
                    <p v-if="section.label" class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-[0.16em] text-tertiary/80">{{ section.label }}</p>
                    <div class="space-y-1">
                        <SidebarLink
                            v-for="item in section.items" :key="item.name"
                            :href="hrefFor(item)" :active="isActive(item)" :icon="item.icon"
                            :disabled="!hasRoute(item)"
                        >{{ item.name }}</SidebarLink>
                    </div>
                </div>
            </nav>

            <div class="m-3 rounded-xl border border-tertiary/25 bg-gradient-to-br from-white/5 to-tertiary/10 p-4">
                <p class="text-xs font-semibold text-tertiary">Admin Panel</p>
                <p class="mt-0.5 text-xs text-white/55">Shaheen Group of Travels</p>
            </div>
        </aside>

        <!-- Main -->
        <div class="lg:pl-72">
            <header class="sticky top-0 z-30 flex h-20 items-center gap-4 border-b border-slate-200/70 bg-white/85 px-4 backdrop-blur sm:px-6 lg:px-8">
                <button class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden" aria-label="Open menu" @click="mobileOpen = true">
                    <Bars3Icon class="size-6" />
                </button>
                <div class="min-w-0 flex-1">
                    <h1 v-if="title" class="truncate text-lg font-semibold sm:text-xl">{{ title }}</h1>
                    <p v-if="subtitle" class="truncate text-xs text-slate-500 sm:text-sm">{{ subtitle }}</p>
                </div>
                <slot name="actions" />
                <NotificationBell />
                <UserMenu />
            </header>

            <main class="px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                <slot />
            </main>
        </div>
    </div>
</template>
