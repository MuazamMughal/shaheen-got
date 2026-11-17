<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import {
    PlusIcon, MagnifyingGlassIcon, PencilSquareIcon, TrashIcon, DocumentDuplicateIcon,
    EyeIcon, EyeSlashIcon, FunnelIcon, XMarkIcon, TicketIcon,
} from '@heroicons/vue/24/outline';
import RowActions from '@/Components/ui/RowActions.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import Badge from '@/Components/ui/Badge.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import SeatsBar from '@/Components/flights/SeatsBar.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { formatPKR } from '@/Composables/useMoney';

const props = defineProps({
    flights: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    options: { type: Object, required: true },
    counts: { type: Object, required: true },
});

const { can, canViewPrices } = usePermissions();

const filters = reactive({
    search: props.filters.search ?? '',
    category: props.filters.category ?? '',
    sector: props.filters.sector ?? '',
    airline: props.filters.airline ?? '',
    status: props.filters.status ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
    past: props.filters.past ?? '',
});
const showFilters = ref(Object.entries(filters).some(([k, v]) => v && k !== 'search' && k !== 'status'));

const sectorOptions = computed(() =>
    props.options.sectors.filter((s) => !filters.category || s.category_id === Number(filters.category)),
);
watch(() => filters.category, () => {
    if (filters.sector && !sectorOptions.value.some((s) => s.value === Number(filters.sector))) filters.sector = '';
});

let timer;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        const params = { ...filters, sort: props.filters.sort, direction: props.filters.direction };
        router.get(route('admin.flights.index'), Object.fromEntries(Object.entries(params).filter(([, v]) => v)), {
            preserveState: true, preserveScroll: true, replace: true,
        });
    }, 300);
});

const activeFilterCount = computed(() => ['category', 'sector', 'airline', 'from', 'to', 'past'].filter((k) => filters[k]).length);
function clearFilters() {
    Object.assign(filters, { category: '', sector: '', airline: '', from: '', to: '', past: '' });
}

const statusChips = computed(() => [
    { value: '', label: 'All upcoming' },
    { value: 'live', label: 'Live', count: props.counts.live },
    { value: 'draft', label: 'Draft', count: props.counts.draft },
    { value: 'sold_out', label: 'Sold out', count: props.counts.sold_out },
    { value: 'closed', label: 'Closed' },
]);

const columns = computed(() => [
    { key: 'departure_date', label: 'Date', sortable: true },
    { key: 'sector', label: 'Sector / Airline' },
    { key: 'flight_code', label: 'Flight', sortable: true, class: 'hidden xl:table-cell' },
    { key: 'time', label: 'Time', class: 'hidden 2xl:table-cell' },
    { key: 'bag', label: 'Bag / Meal', class: 'hidden 2xl:table-cell' },
    ...(canViewPrices() ? [{ key: 'fare_price', label: 'Fare', align: 'right' }] : []),
    { key: 'seats', label: 'Seats', class: 'hidden xl:table-cell' },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'actions', label: '', align: 'right' },
]);

const deleting = ref(null);
const busy = ref(false);
function destroy() {
    busy.value = true;
    router.delete(route('admin.flights.destroy', deleting.value.id), {
        preserveScroll: true,
        onFinish: () => { busy.value = false; deleting.value = null; },
    });
}

const publishing = ref(null);
function togglePublish(row) {
    publishing.value = row.id;
    router.patch(route('admin.flights.publish', row.id), {}, { preserveScroll: true, onFinish: () => (publishing.value = null) });
}
const isPublished = (row) => ['live', 'sold_out'].includes(row.status);
</script>

<template>
    <AdminLayout title="Flights" subtitle="Group inventory published to agents">
        <template #actions>
            <Button v-if="can('flights.create') && canViewPrices()" :href="route('admin.flights.create')" class="hidden sm:inline-flex"><PlusIcon class="size-4" /> Add flight</Button>
        </template>

        <!-- Status chips -->
        <div class="-mx-1 mb-4 flex gap-2 overflow-x-auto px-1 pb-1">
            <button
                v-for="chip in statusChips" :key="chip.value" type="button" @click="filters.status = chip.value"
                :class="['inline-flex shrink-0 items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold transition',
                    filters.status === chip.value ? 'bg-secondary text-white shadow-sm' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:ring-slate-300']"
            >
                {{ chip.label }}
                <span v-if="chip.count !== undefined" :class="['rounded-full px-1.5 text-xs', filters.status === chip.value ? 'bg-tertiary text-secondary-900' : 'bg-slate-100 text-slate-600']">{{ chip.count }}</span>
            </button>
        </div>

        <div class="mb-4 flex flex-col gap-3 sm:flex-row">
            <Input v-model="filters.search" placeholder="Search flight code or PNR" aria-label="Search flights" class="flex-1 sm:max-w-sm">
                <template #icon><MagnifyingGlassIcon class="size-5" /></template>
            </Input>
            <Button variant="secondary" :aria-expanded="showFilters" @click="showFilters = !showFilters">
                <FunnelIcon class="size-4" /> Filters
                <span v-if="activeFilterCount" class="rounded-full bg-tertiary px-1.5 text-xs text-secondary-900">{{ activeFilterCount }}</span>
            </Button>
            <Button v-if="can('flights.create') && canViewPrices()" :href="route('admin.flights.create')" class="sm:hidden" block><PlusIcon class="size-4" /> Add flight</Button>
        </div>

        <div v-if="showFilters" class="card mb-5 grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4">
            <Select v-model="filters.category" label="Category" :options="[{ value: '', label: 'All' }, ...options.categories]" />
            <Select v-model="filters.sector" label="Sector" :options="[{ value: '', label: 'All' }, ...sectorOptions]" />
            <Select v-model="filters.airline" label="Airline" :options="[{ value: '', label: 'All' }, ...options.airlines]" />
            <Select v-model="filters.status" label="Status" :options="[{ value: '', label: 'All' }, ...options.statuses]" />
            <Input v-model="filters.from" type="date" label="Departure from" />
            <Input v-model="filters.to" type="date" label="Departure to" />
            <label class="flex items-center gap-2 self-end pb-3 text-sm text-slate-600">
                <input v-model="filters.past" type="checkbox" true-value="1" false-value="" class="size-4 rounded border-slate-300 text-secondary focus:ring-secondary-200" />
                Include past flights
            </label>
            <div class="flex items-end justify-end">
                <Button variant="ghost" :disabled="!activeFilterCount" @click="clearFilters"><XMarkIcon class="size-4" /> Clear filters</Button>
            </div>
        </div>

        <DataTable
            :columns="columns" :rows="flights" :sort="filters.sort ?? props.filters.sort ?? 'departure_date'" :direction="props.filters.direction ?? 'asc'"
            empty-title="No flights found" empty-description="Add a flight or change the filters."
        >
            <template #cell-departure_date="{ row }">
                <div class="whitespace-nowrap text-sm font-semibold text-slate-900">
                    <p>{{ row.departure_date_label }}</p>
                    <p class="text-xs font-normal text-slate-500">{{ row.arrival_date_label }}</p>
                </div>
            </template>
            <template #cell-sector="{ row }">
                <div class="flex items-center gap-3">
                    <span class="hidden h-9 w-12 shrink-0 place-items-center overflow-hidden rounded-lg border border-slate-100 bg-white sm:grid">
                        <img v-if="row.airline?.logo_url" :src="row.airline.logo_url" :alt="row.airline.name" class="max-h-7 max-w-10 object-contain" />
                        <span v-else class="text-xs font-bold text-secondary">{{ row.airline?.iata_code ?? '✈' }}</span>
                    </span>
                    <div class="min-w-40 max-w-64 whitespace-normal">
                        <p class="text-sm font-semibold tracking-wide text-slate-900">{{ row.sector?.name }}</p>
                        <p class="text-xs text-slate-500">{{ row.airline?.name }} · {{ row.category?.name }}</p>
                        <p class="text-xs text-slate-500 xl:hidden">{{ row.flight_code }} · {{ row.departure_time }}–{{ row.arrival_time }} · {{ row.seats_available }}/{{ row.total_seats }} seats left</p>
                    </div>
                </div>
            </template>
            <template #cell-flight_code="{ row }">
                <p class="whitespace-nowrap font-medium text-slate-900">{{ row.flight_code }}</p>
                <p v-if="row.pnr" class="text-xs text-slate-500">PNR {{ row.pnr }}</p>
            </template>
            <template #cell-time="{ row }">
                <span class="whitespace-nowrap">{{ row.departure_time }} – {{ row.arrival_time }}</span>
            </template>
            <template #cell-bag="{ row }">
                <p class="whitespace-nowrap">{{ row.baggage_type?.label }}</p>
                <p :class="['text-xs font-bold', row.meal ? 'text-emerald-600' : 'text-rose-500']">Meal {{ row.meal ? 'YES' : 'NO' }}</p>
            </template>
            <template #cell-fare_price="{ row }">
                <span class="whitespace-nowrap font-display font-semibold text-secondary">{{ formatPKR(row.fare_price) }}</span>
            </template>
            <template #cell-seats="{ row }">
                <SeatsBar :total="row.total_seats" :confirmed="row.seats_confirmed" :pending="row.seats_pending" compact />
            </template>
            <template #cell-status="{ row }">
                <Badge :color="row.status_color" dot>{{ row.status_label }}</Badge>
            </template>
            <template #cell-actions="{ row }">
                <!-- Compact menu below xl -->
                <div class="xl:hidden">
                    <RowActions :label="`Actions for ${row.flight_code}`">
                        <button v-if="can('flights.publish') && row.status !== 'departed'" type="button" role="menuitem" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 focus:bg-slate-50 focus:outline-none" @click="togglePublish(row)">
                            <component :is="isPublished(row) ? EyeSlashIcon : EyeIcon" class="size-4" /> {{ isPublished(row) ? 'Unpublish' : 'Publish' }}
                        </button>
                        <Link v-if="can('flights.create') && canViewPrices()" :href="route('admin.flights.duplicate', row.id)" role="menuitem" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 focus:bg-slate-50 focus:outline-none"><DocumentDuplicateIcon class="size-4" /> Duplicate</Link>
                        <Link v-if="can('bookings.view')" :href="route('admin.flights.bookings', row.id)" role="menuitem" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 focus:bg-slate-50 focus:outline-none"><TicketIcon class="size-4" /> Bookings</Link>
                        <Link v-if="can('flights.edit')" :href="route('admin.flights.edit', row.id)" role="menuitem" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 focus:bg-slate-50 focus:outline-none"><PencilSquareIcon class="size-4" /> Edit</Link>
                        <button v-if="can('flights.delete')" type="button" role="menuitem" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 focus:bg-slate-50 focus:outline-none !text-rose-600 hover:!bg-rose-50" @click="deleting = row"><TrashIcon class="size-4" /> Delete</button>
                    </RowActions>
                </div>
                <div class="hidden justify-end gap-0.5 xl:flex">
                    <button
                        v-if="can('flights.publish') && row.status !== 'departed'" type="button" :disabled="publishing === row.id"
                        :class="['grid size-8 place-items-center rounded-lg transition disabled:opacity-40', isPublished(row) ? 'text-emerald-600 hover:bg-amber-50 hover:text-amber-600' : 'text-slate-400 hover:bg-emerald-50 hover:text-emerald-600']"
                        :aria-label="isPublished(row) ? `Unpublish ${row.flight_code}` : `Publish ${row.flight_code}`"
                        :title="isPublished(row) ? 'Unpublish' : 'Publish'" @click="togglePublish(row)"
                    >
                        <EyeIcon v-if="isPublished(row)" class="size-5" />
                        <EyeSlashIcon v-else class="size-5" />
                    </button>
                    <Link v-if="can('bookings.view')" :href="route('admin.flights.bookings', row.id)" class="grid size-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-secondary" :aria-label="`Bookings for ${row.flight_code}`" title="Bookings">
                        <TicketIcon class="size-5" />
                    </Link>
                    <Link v-if="can('flights.create') && canViewPrices()" :href="route('admin.flights.duplicate', row.id)" class="grid size-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-secondary" :aria-label="`Duplicate ${row.flight_code}`" title="Duplicate">
                        <DocumentDuplicateIcon class="size-5" />
                    </Link>
                    <Link v-if="can('flights.edit')" :href="route('admin.flights.edit', row.id)" class="grid size-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-secondary" :aria-label="`Edit ${row.flight_code}`" title="Edit">
                        <PencilSquareIcon class="size-5" />
                    </Link>
                    <button v-if="can('flights.delete')" type="button" class="grid size-8 place-items-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600" :aria-label="`Delete ${row.flight_code}`" title="Delete" @click="deleting = row">
                        <TrashIcon class="size-5" />
                    </button>
                </div>
            </template>
        </DataTable>

        <ConfirmDialog
            :show="!!deleting" :loading="busy" title="Delete flight?" confirm-text="Delete flight"
            :message="deleting ? `${deleting.sector?.name} on ${deleting.departure_date_label} (${deleting.flight_code}) will be removed from listings.` : ''"
            @close="deleting = null" @confirm="destroy"
        />
    </AdminLayout>
</template>
