<script setup>
import { Link } from '@inertiajs/vue3';
import { CheckBadgeIcon, ClockIcon, XCircleIcon, ArrowUpRightIcon, Squares2X2Icon, TicketIcon } from '@heroicons/vue/24/outline';
import AgentLayout from '@/Layouts/AgentLayout.vue';
import StatCard from '@/Components/ui/StatCard.vue';

defineProps({
    stats: { type: Object, required: true },
    totalGroups: { type: Number, default: 0 },
    featured: { type: Array, default: () => [] },
});
</script>

<template>
    <AgentLayout title="Dashboard">
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-secondary via-secondary-700 to-secondary p-6 sm:p-10">
            <div class="pointer-events-none absolute -right-16 -top-16 size-72 rounded-full border border-tertiary/25" />
            <div class="pointer-events-none absolute -right-4 -top-4 size-44 rounded-full border border-tertiary/30" />
            <h2 class="relative text-center font-display text-2xl font-semibold !text-white sm:text-4xl">Manage your Agent Dashboard</h2>
            <p class="relative mt-2 text-center text-sm text-white/70">Welcome back, {{ $page.props.auth.user.name }}</p>
        </div>

        <div class="mt-6 grid gap-5 sm:grid-cols-3">
            <StatCard label="Confirmed Bookings" :value="stats.confirmed" tone="green" :href="route('agent.bookings.index', { status: 'confirmed' })"><template #icon><CheckBadgeIcon class="size-6" /></template></StatCard>
            <StatCard label="Pending Bookings" :value="stats.pending" tone="amber" hint="Includes payment approved" :href="route('agent.bookings.index', { status: 'pending' })"><template #icon><ClockIcon class="size-6" /></template></StatCard>
            <StatCard label="Cancelled / Rejected" :value="stats.cancelled" tone="red" :href="route('agent.bookings.index', { status: 'cancelled' })"><template #icon><XCircleIcon class="size-6" /></template></StatCard>
        </div>

        <div class="mt-6 grid gap-5 md:grid-cols-3">
            <!-- All groups -->
            <Link :href="route('agent.groups.index')" class="card group relative overflow-hidden transition hover:-translate-y-0.5 hover:shadow-xl">
                <div class="relative h-36 bg-gradient-to-br from-secondary-600 to-secondary-900">
                    <div class="absolute inset-0 bg-[radial-gradient(circle_at_80%_20%,var(--color-tertiary)_0,transparent_45%)] opacity-40" />
                    <Squares2X2Icon class="absolute bottom-4 left-5 size-10 text-white/80" />
                </div>
                <div class="flex items-center justify-between p-5">
                    <div>
                        <h3 class="font-display font-semibold">All Groups</h3>
                        <p class="mt-0.5 text-sm text-slate-500">{{ totalGroups }} groups available now</p>
                    </div>
                    <span class="grid size-9 place-items-center rounded-full bg-tertiary-50 text-tertiary-700 transition group-hover:bg-tertiary group-hover:text-white"><ArrowUpRightIcon class="size-4" /></span>
                </div>
            </Link>

            <!-- Busiest categories -->
            <Link v-for="c in featured" :key="c.id" :href="route('agent.groups.show', c.slug)" class="card group relative overflow-hidden transition hover:-translate-y-0.5 hover:shadow-xl">
                <div class="relative h-36 bg-gradient-to-br from-secondary-600 to-secondary-900">
                    <img v-if="c.image_url" :src="c.image_url" alt="" class="absolute inset-0 size-full object-cover transition duration-500 group-hover:scale-105" />
                </div>
                <div class="flex items-center justify-between p-5">
                    <div>
                        <h3 class="font-display font-semibold">{{ c.name }}</h3>
                        <p class="mt-0.5 text-sm text-slate-500">{{ c.flights_count }} groups available</p>
                    </div>
                    <span class="grid size-9 place-items-center rounded-full bg-tertiary-50 text-tertiary-700 transition group-hover:bg-tertiary group-hover:text-white"><ArrowUpRightIcon class="size-4" /></span>
                </div>
            </Link>

            <!-- My bookings -->
            <Link v-if="featured.length < 2" :href="route('agent.bookings.index')" class="card group relative overflow-hidden transition hover:-translate-y-0.5 hover:shadow-xl">
                <div class="relative h-36 bg-gradient-to-br from-tertiary-600 to-tertiary-700"><TicketIcon class="absolute bottom-4 left-5 size-10 text-white/90" /></div>
                <div class="flex items-center justify-between p-5">
                    <div>
                        <h3 class="font-display font-semibold">My Bookings</h3>
                        <p class="mt-0.5 text-sm text-slate-500">Track payments and confirmations</p>
                    </div>
                    <span class="grid size-9 place-items-center rounded-full bg-tertiary-50 text-tertiary-700 transition group-hover:bg-tertiary group-hover:text-white"><ArrowUpRightIcon class="size-4" /></span>
                </div>
            </Link>
        </div>
    </AgentLayout>
</template>
