<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ArrowLeftIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from '@/Components/ui/Button.vue';
import Badge from '@/Components/ui/Badge.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import SeatsBar from '@/Components/flights/SeatsBar.vue';
import BookingStatusBadge from '@/Components/bookings/BookingStatusBadge.vue';
import AdminBookingMenu from '@/Components/bookings/AdminBookingMenu.vue';
import BookingActionDialog from '@/Components/bookings/BookingActionDialog.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { formatPKR } from '@/Composables/useMoney';

defineProps({
    flight: { type: Object, required: true },
    bookings: { type: Array, required: true },
});

const { can, canViewPrices } = usePermissions();
const action = ref(null);
</script>

<template>
    <AdminLayout :title="`${flight.sector?.name} · ${flight.departure_date_label}`" subtitle="Every booking competing for this group's seats">
        <template #actions>
            <Button variant="ghost" :href="route('admin.flights.index')" class="hidden sm:inline-flex"><ArrowLeftIcon class="size-4" /> Flights</Button>
        </template>

        <div class="mx-auto max-w-6xl space-y-6">
            <section class="card grid gap-6 p-6 md:grid-cols-[1fr_320px] md:items-center">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="font-display text-xl font-semibold">{{ flight.flight_code }}</h2>
                        <Badge :color="flight.status_color" dot>{{ flight.status_label }}</Badge>
                    </div>
                    <p class="mt-1 text-sm text-slate-500">{{ flight.airline?.name }} · {{ flight.departure_date_label }} → {{ flight.arrival_date_label }} · {{ flight.departure_time }}–{{ flight.arrival_time }}</p>
                    <p v-if="flight.pnr" class="mt-1 text-sm text-slate-500">PNR <strong class="text-slate-800">{{ flight.pnr }}</strong></p>
                    <Button v-if="can('flights.edit')" size="sm" variant="ghost" class="mt-3 -ml-3" :href="route('admin.flights.edit', flight.id)">Edit flight / add seats</Button>
                </div>
                <SeatsBar :total="flight.total_seats" :confirmed="flight.seats_confirmed" :pending="flight.seats_pending" />
            </section>

            <div v-if="flight.seats_pending > flight.seats_available" class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                <ExclamationTriangleIcon class="size-5 shrink-0" />
                <p>Open requests need <strong>{{ flight.seats_pending }}</strong> seats but only <strong>{{ flight.seats_available }}</strong> are left. Approve payments and confirm in your chosen order, then reject the rest.</p>
            </div>

            <section class="card overflow-hidden">
                <ul v-if="bookings.length" class="divide-y divide-slate-100">
                    <li v-for="b in bookings" :key="b.id" class="flex flex-wrap items-center gap-4 px-5 py-4">
                        <div class="min-w-0 flex-1">
                            <Link :href="route('admin.bookings.show', b.id)" class="font-semibold text-secondary hover:text-tertiary-600">{{ b.booking_no }}</Link>
                            <p class="truncate text-sm text-slate-700">{{ b.agent?.agency_name ?? b.agent?.name }} · {{ b.lead_passenger }}</p>
                            <p class="text-xs text-slate-500">{{ b.created_at_time }} · {{ b.seats_needed }} seat(s){{ b.infants ? ` + ${b.infants} infant` : '' }}</p>
                        </div>
                        <span v-if="canViewPrices()" class="font-display font-semibold text-secondary">{{ formatPKR(b.total_price) }}</span>
                        <div>
                            <BookingStatusBadge :booking="b" with-payment />
                            <p v-if="b.exceeds_seats" class="mt-1 text-[11px] font-semibold text-rose-600">Doesn't fit remaining seats</p>
                        </div>
                        <AdminBookingMenu :booking="b" :show-flight-link="false" @action="action = $event" />
                    </li>
                </ul>
                <EmptyState v-else title="No bookings on this flight yet" />
            </section>
        </div>

        <BookingActionDialog :action="action" @close="action = null" />
    </AdminLayout>
</template>
