<script setup>
import { Link } from '@inertiajs/vue3';
import { UsersIcon, UserPlusIcon, PaperAirplaneIcon, BanknotesIcon, CheckBadgeIcon, ClockIcon, TicketIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatCard from '@/Components/ui/StatCard.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import BookingStatusBadge from '@/Components/bookings/BookingStatusBadge.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { formatPKR } from '@/Composables/useMoney';

defineProps({
    stats: { type: Object, default: null },
    recentBookings: { type: Array, default: () => [] },
    lowSeatFlights: { type: Array, default: () => [] },
});

const { can, canViewPrices } = usePermissions();
</script>

<template>
    <AdminLayout title="Dashboard" subtitle="Overview of agents, flights and bookings">
        <div class="relative overflow-hidden rounded-2xl bg-secondary p-6 sm:p-8">
            <div class="pointer-events-none absolute -right-20 -top-20 size-72 rounded-full border border-tertiary/20" />
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-tertiary">Admin Panel</p>
            <h2 class="mt-2 font-display text-2xl font-semibold !text-white sm:text-3xl">Welcome, {{ $page.props.auth.user.name }}</h2>
            <p class="mt-2 max-w-xl text-sm text-white/70">Manage group inventory, review agent bookings and keep seats moving.</p>
        </div>

        <template v-if="stats">
            <!-- Work queue -->
            <div class="mt-6 grid gap-5 sm:grid-cols-3">
                <StatCard label="Awaiting payment" :value="stats.pendingPayments" tone="amber" hint="Pending bookings" :href="can('bookings.view') ? route('admin.bookings.index', { status: 'pending' }) : null"><template #icon><BanknotesIcon class="size-6" /></template></StatCard>
                <StatCard label="Ready to confirm" :value="stats.awaitingConfirmation" tone="navy" hint="Payment approved" :href="can('bookings.view') ? route('admin.bookings.index', { status: 'payment_approved' }) : null"><template #icon><ClockIcon class="size-6" /></template></StatCard>
                <StatCard label="Confirmed today" :value="stats.confirmedToday" tone="green" :href="can('bookings.view') ? route('admin.bookings.index', { status: 'confirmed' }) : null"><template #icon><CheckBadgeIcon class="size-6" /></template></StatCard>
            </div>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <StatCard v-if="stats.revenueMonth !== null" label="Confirmed revenue this month" :value="formatPKR(stats.revenueMonth)" tone="gold"><template #icon><BanknotesIcon class="size-6" /></template></StatCard>
                <StatCard label="Seats sold this month" :value="stats.seatsSoldMonth" tone="navy" :class="stats.revenueMonth === null && 'sm:col-span-2'"><template #icon><TicketIcon class="size-6" /></template></StatCard>
            </div>
            <div class="mt-5 grid gap-5 sm:grid-cols-3">
                <StatCard label="Live flights" :value="stats.liveFlights" tone="gold" hint="Bookable by agents now" :href="can('flights.view') ? route('admin.flights.index', { status: 'live' }) : null"><template #icon><PaperAirplaneIcon class="size-6" /></template></StatCard>
                <StatCard label="Approved agents" :value="stats.agents" tone="navy" :href="can('agents.view') ? route('admin.agents.index', { status: 'approved' }) : null"><template #icon><UsersIcon class="size-6" /></template></StatCard>
                <StatCard label="Agents to approve" :value="stats.pendingAgents" tone="amber" hint="New sign-ups" :href="can('agents.view') ? route('admin.agents.index', { status: 'pending' }) : null"><template #icon><UserPlusIcon class="size-6" /></template></StatCard>
            </div>
        </template>

        <!-- Flights about to sell out -->
        <div v-if="lowSeatFlights.length" class="card mt-6 overflow-hidden">
            <div class="flex items-center gap-2 border-b border-slate-100 px-6 py-4">
                <ExclamationTriangleIcon class="size-5 text-amber-500" />
                <h3 class="font-semibold">Flights about to sell out</h3>
            </div>
            <ul class="divide-y divide-slate-100">
                <li v-for="f in lowSeatFlights" :key="f.id">
                    <component :is="can('bookings.view') ? Link : 'div'" :href="can('bookings.view') ? route('admin.flights.bookings', f.id) : undefined" class="flex flex-wrap items-center gap-x-4 gap-y-1 px-6 py-3 transition hover:bg-tertiary-50/40">
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-semibold text-slate-900">{{ f.sector }}</span>
                            <span class="block text-xs text-slate-500">{{ f.airline }} · {{ f.flight_code }} · {{ f.departure }}</span>
                        </span>
                        <span class="text-sm"><strong class="text-amber-700">{{ f.seats_available }}</strong> <span class="text-slate-500">of {{ f.total_seats }} left</span></span>
                        <span v-if="f.seats_pending" class="text-xs text-slate-500">{{ f.seats_pending }} requested</span>
                    </component>
                </li>
            </ul>
        </div>

        <div v-if="can('bookings.view')" class="card mt-6 overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <h3 class="font-semibold">Recent bookings</h3>
                <Link :href="route('admin.bookings.index', { status: '' })" class="text-sm font-semibold text-secondary hover:text-tertiary-600">View all</Link>
            </div>
            <ul v-if="recentBookings.length" class="divide-y divide-slate-100">
                <li v-for="b in recentBookings" :key="b.id">
                    <Link :href="route('admin.bookings.show', b.id)" class="flex flex-wrap items-center gap-x-4 gap-y-1 px-6 py-3 transition hover:bg-tertiary-50/40">
                        <span class="w-24 font-semibold text-secondary">{{ b.booking_no }}</span>
                        <span class="min-w-0 flex-1 truncate text-sm text-slate-700">{{ b.agent?.agency_name ?? b.agent?.name }} · {{ b.flight.sector }}</span>
                        <span class="text-xs text-slate-500">{{ b.total_pax }} pax</span>
                        <span v-if="canViewPrices()" class="w-28 text-right font-display text-sm font-semibold text-secondary">{{ formatPKR(b.total_price) }}</span>
                        <BookingStatusBadge :booking="b" />
                    </Link>
                </li>
            </ul>
            <EmptyState v-else title="No bookings yet" description="Bookings from agents will appear here once flights are live.">
                <template #icon><TicketIcon class="size-7" /></template>
            </EmptyState>
        </div>
    </AdminLayout>
</template>
