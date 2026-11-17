<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { MagnifyingGlassIcon, EyeIcon, PencilSquareIcon, XCircleIcon, ArrowDownTrayIcon, PlusIcon } from '@heroicons/vue/24/outline';
import AgentLayout from '@/Layouts/AgentLayout.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import Button from '@/Components/ui/Button.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import RowActions from '@/Components/ui/RowActions.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import BookingStatusBadge from '@/Components/bookings/BookingStatusBadge.vue';
import WhatsAppButton from '@/Components/WhatsAppButton.vue';
import { formatPKR } from '@/Composables/useMoney';

const props = defineProps({
    bookings: { type: Object, required: true },
    counts: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    sectors: { type: Array, default: () => [] },
});

const adminWhatsapp = computed(() => usePage().props.settings?.contact?.whatsapp);

const filters = reactive({
    status: props.filters.status ?? '', search: props.filters.search ?? '', sector: props.filters.sector ?? '',
    from: props.filters.from ?? '', to: props.filters.to ?? '',
});
let timer;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => router.get(route('agent.bookings.index'), Object.fromEntries(Object.entries(filters).filter(([, v]) => v)), {
        preserveState: true, preserveScroll: true, replace: true,
    }), 300);
});

const tabs = [
    { value: '', label: 'All', key: 'all' },
    { value: 'pending', label: 'Pending' },
    { value: 'payment_approved', label: 'Payment approved' },
    { value: 'confirmed', label: 'Confirmed' },
    { value: 'rejected', label: 'Rejected' },
    { value: 'cancelled', label: 'Cancelled' },
];

const columns = [
    { key: 'created_at', label: 'Date', class: 'hidden whitespace-nowrap xl:table-cell' },
    { key: 'booking_no', label: 'Booking ID' },
    { key: 'agency', label: 'Agency', class: 'hidden 2xl:table-cell' },
    { key: 'pnr', label: 'PNR', class: 'hidden xl:table-cell' },
    { key: 'airline', label: 'Airline', class: 'hidden 2xl:table-cell' },
    { key: 'sector', label: 'Sector' },
    { key: 'lead', label: 'Lead passenger', class: 'hidden 2xl:table-cell' },
    { key: 'travel', label: 'Travel date', class: 'hidden lg:table-cell' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '', align: 'right' },
];

const cancelling = ref(null);
const busy = ref(false);
function cancel() {
    busy.value = true;
    router.patch(route('agent.bookings.cancel', cancelling.value.id), {}, {
        preserveScroll: true, onFinish: () => { busy.value = false; cancelling.value = null; },
    });
}

const waText = (b) => [
    'Assalam o Alaikum, payment for my booking:',
    `Booking: ${b.booking_no}`, `Sector: ${b.flight.sector}`,
    `Flight: ${b.flight.flight_code} · ${b.flight.departure_date_label}`,
    `Passengers: ${b.total_pax}`, `Lead: ${b.lead_passenger ?? b.booker_name}`,
    `Total: ${formatPKR(b.total_price)}`, 'Payment proof attached.',
].join('\n');

const item = 'flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 focus:bg-slate-50 focus:outline-none';
</script>

<template>
    <AgentLayout title="My Bookings">
        <div class="mb-4 flex flex-col-reverse gap-3 xl:flex-row xl:items-center xl:justify-between">
            <div class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1" role="tablist">
                <button
                    v-for="t in tabs" :key="t.value" type="button" role="tab" :aria-selected="filters.status === t.value" @click="filters.status = t.value"
                    :class="['inline-flex shrink-0 items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold transition',
                        filters.status === t.value ? 'bg-secondary text-white shadow-sm' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:ring-slate-300']"
                >
                    {{ t.label }}
                    <span :class="['rounded-full px-1.5 text-xs', filters.status === t.value ? 'bg-tertiary text-secondary-900' : 'bg-slate-100 text-slate-600']">{{ counts[t.key ?? t.value] }}</span>
                </button>
            </div>
            <Button variant="gold" :href="route('agent.groups.index')" class="shrink-0 self-end xl:self-auto"><PlusIcon class="size-4" /> New booking</Button>
        </div>

        <div class="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-[1fr_220px_160px_160px]">
            <Input v-model="filters.search" placeholder="Booking ID, passenger, passport or PNR" aria-label="Search bookings">
                <template #icon><MagnifyingGlassIcon class="size-5" /></template>
            </Input>
            <Select v-model="filters.sector" :options="[{ value: '', label: 'All sectors' }, ...sectors]" aria-label="Filter by sector" />
            <Input v-model="filters.from" type="date" aria-label="Booked from" title="Booked from" />
            <Input v-model="filters.to" type="date" aria-label="Booked to" title="Booked to" />
        </div>

        <DataTable :columns="columns" :rows="bookings" empty-title="No bookings yet" empty-description="Pick a group and book seats for your customers.">
            <template #cell-booking_no="{ row }">
                <Link :href="route('agent.bookings.show', row.id)" class="font-semibold text-secondary hover:text-tertiary-600">{{ row.booking_no }}</Link>
                <p class="whitespace-nowrap text-xs text-slate-500 xl:hidden">{{ row.created_at }}</p>
            </template>
            <template #cell-agency="{ row }">{{ row.agent?.agency_name }}</template>
            <template #cell-pnr="{ row }"><span class="font-mono">{{ row.flight.pnr ?? '—' }}</span></template>
            <template #cell-airline="{ row }">{{ row.flight.airline?.name }}</template>
            <template #cell-sector="{ row }">
                <p class="whitespace-nowrap font-medium text-slate-900">{{ row.flight.sector }}</p>
                <p class="text-xs text-slate-500">{{ row.flight.flight_code }} · {{ row.total_pax }} pax<span class="2xl:hidden"> · {{ row.lead_passenger }}</span></p>
            </template>
            <template #cell-lead="{ row }">{{ row.lead_passenger }}</template>
            <template #cell-travel="{ row }"><span class="whitespace-nowrap">{{ row.flight.departure_date_label }}</span></template>
            <template #cell-status="{ row }"><BookingStatusBadge :booking="row" with-payment /></template>
            <template #cell-actions="{ row }">
                <div class="flex items-center justify-end gap-1">
                    <WhatsAppButton v-if="row.status === 'pending'" :number="adminWhatsapp" :text="waText(row)" size="icon" label="Send payment proof on WhatsApp" />
                    <RowActions :label="`Actions for ${row.booking_no}`">
                        <Link :href="route('agent.bookings.show', row.id)" role="menuitem" :class="item"><EyeIcon class="size-4" /> View</Link>
                        <a :href="route('agent.bookings.pdf', row.id)" role="menuitem" :class="item"><ArrowDownTrayIcon class="size-4" /> Export PDF</a>
                        <template v-if="row.status === 'pending'">
                            <Link :href="route('agent.bookings.edit', row.id)" role="menuitem" :class="item"><PencilSquareIcon class="size-4" /> Edit</Link>
                            <button type="button" role="menuitem" :class="[item, '!text-rose-600']" @click="cancelling = row"><XCircleIcon class="size-4" /> Cancel booking</button>
                        </template>
                    </RowActions>
                </div>
            </template>
        </DataTable>

        <ConfirmDialog
            :show="!!cancelling" :loading="busy" title="Cancel this booking?" confirm-text="Cancel booking" cancel-text="Keep it"
            :message="cancelling ? `${cancelling.booking_no} will be cancelled. This can't be undone.` : ''"
            @close="cancelling = null" @confirm="cancel"
        />
    </AgentLayout>
</template>
