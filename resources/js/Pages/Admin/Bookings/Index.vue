<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { MagnifyingGlassIcon, FunnelIcon, XMarkIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import Button from '@/Components/ui/Button.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import BookingStatusBadge from '@/Components/bookings/BookingStatusBadge.vue';
import AdminBookingMenu from '@/Components/bookings/AdminBookingMenu.vue';
import BookingActionDialog from '@/Components/bookings/BookingActionDialog.vue';
import WhatsAppButton from '@/Components/WhatsAppButton.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { formatPKR } from '@/Composables/useMoney';

const props = defineProps({
    bookings: { type: Object, required: true },
    counts: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    options: { type: Object, required: true },
});

const { can, canViewPrices } = usePermissions();

const filters = reactive({
    status: props.filters.status ?? '', search: props.filters.search ?? '', sector: props.filters.sector ?? '',
    airline: props.filters.airline ?? '', from: props.filters.from ?? '', to: props.filters.to ?? '',
    travel_from: props.filters.travel_from ?? '', travel_to: props.filters.travel_to ?? '',
});
const extraKeys = ['sector', 'airline', 'from', 'to', 'travel_from', 'travel_to'];
const showFilters = ref(extraKeys.some((k) => filters[k]));
const activeCount = computed(() => extraKeys.filter((k) => filters[k]).length);
const clear = () => extraKeys.forEach((k) => (filters[k] = ''));

let timer;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => router.get(route('admin.bookings.index'), Object.fromEntries(Object.entries(filters).filter(([, v]) => v)), {
        preserveState: true, preserveScroll: true, replace: true,
    }), 300);
});

const tabs = [
    { value: 'pending', label: 'Pending' },
    { value: 'payment_approved', label: 'Payment approved' },
    { value: 'confirmed', label: 'Confirmed' },
    { value: 'rejected', label: 'Rejected' },
    { value: 'cancelled', label: 'Cancelled' },
    { value: '', label: 'All', key: 'all' },
];

const columns = computed(() => [
    { key: 'booking_no', label: 'Booking' },
    { key: 'agency', label: 'Agency' },
    { key: 'pnr', label: 'PNR', class: 'hidden 2xl:table-cell' },
    { key: 'sector', label: 'Sector / Airline', class: 'hidden xl:table-cell' },
    { key: 'lead', label: 'Lead passenger', class: 'hidden 2xl:table-cell' },
    { key: 'travel', label: 'Travel', class: 'hidden 2xl:table-cell' },
    ...(canViewPrices() ? [{ key: 'total', label: 'Total', align: 'right', class: 'hidden lg:table-cell' }] : []),
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '', align: 'right' },
]);

const action = ref(null);
const quick = (b) => (b.status === 'pending' && can('bookings.approve_payment') ? { type: 'approve-payment', label: 'Approve payment' }
    : b.status === 'payment_approved' && can('bookings.confirm') ? { type: 'confirm', label: 'Confirm' } : null);
</script>

<template>
    <AdminLayout title="Bookings" subtitle="Approve payments and confirm agent bookings">
        <div class="-mx-1 mb-4 flex gap-2 overflow-x-auto px-1 pb-1" role="tablist">
            <button
                v-for="t in tabs" :key="t.value" type="button" role="tab" :aria-selected="filters.status === t.value" @click="filters.status = t.value"
                :class="['inline-flex shrink-0 items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold transition',
                    filters.status === t.value ? 'bg-secondary text-white shadow-sm' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:ring-slate-300']"
            >
                {{ t.label }}
                <span :class="['rounded-full px-1.5 text-xs', filters.status === t.value ? 'bg-tertiary text-secondary-900' : (t.value === 'pending' || t.value === 'payment_approved') && counts[t.value] ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600']">{{ counts[t.key ?? t.value] }}</span>
            </button>
        </div>

        <div class="mb-4 flex flex-col gap-3 sm:flex-row">
            <Input v-model="filters.search" placeholder="Booking ID, agency, passenger, passport or PNR" aria-label="Search bookings" class="flex-1 sm:max-w-md">
                <template #icon><MagnifyingGlassIcon class="size-5" /></template>
            </Input>
            <Button variant="secondary" :aria-expanded="showFilters" @click="showFilters = !showFilters">
                <FunnelIcon class="size-4" /> Filters
                <span v-if="activeCount" class="rounded-full bg-tertiary px-1.5 text-xs text-secondary-900">{{ activeCount }}</span>
            </Button>
        </div>

        <div v-if="showFilters" class="card mb-5 grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4">
            <Select v-model="filters.sector" label="Sector" :options="[{ value: '', label: 'All' }, ...options.sectors]" />
            <Select v-model="filters.airline" label="Airline" :options="[{ value: '', label: 'All' }, ...options.airlines]" />
            <Input v-model="filters.from" type="date" label="Booked from" />
            <Input v-model="filters.to" type="date" label="Booked to" />
            <Input v-model="filters.travel_from" type="date" label="Travel from" />
            <Input v-model="filters.travel_to" type="date" label="Travel to" />
            <div class="flex items-end justify-end lg:col-span-2">
                <Button variant="ghost" :disabled="!activeCount" @click="clear"><XMarkIcon class="size-4" /> Clear filters</Button>
            </div>
        </div>

        <DataTable :columns="columns" :rows="bookings" empty-title="No bookings here" empty-description="Bookings from agents appear in Pending.">
            <template #cell-booking_no="{ row }">
                <Link :href="route('admin.bookings.show', row.id)" class="whitespace-nowrap font-semibold text-secondary hover:text-tertiary-600">{{ row.booking_no }}</Link>
                <p class="whitespace-nowrap text-xs text-slate-500">{{ row.created_at }} · {{ row.total_pax }} pax</p>
            </template>
            <template #cell-agency="{ row }">
                <p class="max-w-56 truncate font-medium text-slate-900">{{ row.agent?.agency_name ?? row.agent?.name }}</p>
                <p class="text-xs text-slate-500 xl:hidden">{{ row.flight.sector }} · {{ row.flight.flight_code }}</p>
                <p class="text-xs text-slate-500 2xl:hidden">Travel {{ row.flight.departure_date_label }} · {{ row.lead_passenger }}</p>
            </template>
            <template #cell-pnr="{ row }"><span class="font-mono">{{ row.flight.pnr ?? '—' }}</span></template>
            <template #cell-sector="{ row }">
                <p class="whitespace-nowrap font-medium text-slate-900">{{ row.flight.sector }}</p>
                <p class="text-xs text-slate-500">{{ row.flight.airline?.name }} · {{ row.flight.flight_code }}</p>
            </template>
            <template #cell-lead="{ row }"><span class="whitespace-nowrap">{{ row.lead_passenger }}</span></template>
            <template #cell-travel="{ row }"><span class="whitespace-nowrap">{{ row.flight.departure_date_label }}</span></template>
            <template #cell-total="{ row }"><span class="whitespace-nowrap font-display font-semibold text-secondary">{{ formatPKR(row.total_price) }}</span></template>
            <template #cell-status="{ row }">
                <BookingStatusBadge :booking="row" />
                <p v-if="row.exceeds_seats" class="mt-1 flex items-center gap-1 text-[11px] font-semibold text-rose-600"><ExclamationTriangleIcon class="size-3.5" /> No seats left</p>
            </template>
            <template #cell-actions="{ row }">
                <div class="flex items-center justify-end gap-1">
                    <button
                        v-if="quick(row)" type="button"
                        :class="['hidden h-8 whitespace-nowrap rounded-lg px-2.5 text-xs font-semibold text-white xl:inline-flex xl:items-center', quick(row).type === 'confirm' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-sky-600 hover:bg-sky-700']"
                        @click="action = { type: quick(row).type, booking: row }"
                    >{{ quick(row).label }}</button>
                    <span class="hidden xl:inline-flex"><WhatsAppButton :number="row.agent?.whatsapp" :text="`Regarding booking ${row.booking_no}`" size="icon" :label="`WhatsApp ${row.agent?.agency_name ?? 'agent'}`" /></span>
                    <AdminBookingMenu :booking="row" @action="action = $event" />
                </div>
            </template>
        </DataTable>

        <BookingActionDialog :action="action" @close="action = null" />
    </AdminLayout>
</template>
