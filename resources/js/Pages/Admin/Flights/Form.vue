<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import {
    ArrowLeftIcon, MapIcon, CalendarDaysIcon, BriefcaseIcon, BanknotesIcon, TicketIcon,
    PaperAirplaneIcon, ClockIcon, DocumentDuplicateIcon,
} from '@heroicons/vue/24/outline';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import TimeInput from '@/Components/ui/TimeInput.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { formatPKR } from '@/Composables/useMoney';
import { focusFirstError, rules as r, today, useValidation } from '@/Composables/useValidation';

const props = defineProps({
    flight: { type: Object, default: null },
    isDuplicate: { type: Boolean, default: false },
    options: { type: Object, required: true },
});

const { can, canViewPrices } = usePermissions();
const editing = computed(() => !!props.flight?.id);
const showPrices = canViewPrices();

const f = props.flight ?? {};
const form = useForm({
    category_id: f.category_id ?? '',
    sector_id: f.sector_id ?? '',
    airline_id: f.airline_id ?? '',
    departure_date: f.departure_date ?? '',
    arrival_date: f.arrival_date ?? '',
    flight_code: f.flight_code ?? '',
    departure_time: f.departure_time ?? '',
    arrival_time: f.arrival_time ?? '',
    baggage_type_id: f.baggage_type_id ?? '',
    meal: f.meal ?? false,
    ...(showPrices ? {
        fare_price: f.fare_price ?? '',
        adult_price: f.adult_price ?? '',
        child_price: f.child_price ?? '',
        infant_price: f.infant_price ?? '',
    } : {}),
    total_seats: f.total_seats ?? '',
    pnr: f.pnr ?? '',
    publish: false,
});

// Sub categories follow the chosen main category.
const sectorOptions = computed(() => props.options.sectors.filter((s) => s.category_id === Number(form.category_id)));
watch(() => form.category_id, () => {
    if (!sectorOptions.value.some((s) => s.value === Number(form.sector_id))) form.sector_id = '';
});
const selectedSector = computed(() => props.options.sectors.find((s) => s.value === Number(form.sector_id)));
const isReturn = computed(() => selectedSector.value?.trip_type !== 'one_way');

// 12 / 24 hour format. Clicking a format also fills default times when empty.
const DEFAULT_TIMES = { departure: '10:00', arrival: '14:00' };
const timeFormat = ref('24');
onMounted(() => {
    try { timeFormat.value = localStorage.getItem('flight-time-format') ?? '24'; } catch { /* storage blocked */ }
});
function setTimeFormat(fmt) {
    timeFormat.value = fmt;
    try { localStorage.setItem('flight-time-format', fmt); } catch { /* storage blocked */ }
    if (!form.departure_time) form.departure_time = DEFAULT_TIMES.departure;
    if (!form.arrival_time) form.arrival_time = DEFAULT_TIMES.arrival;
}

// Arrival can't be before departure: nudge it forward when departure moves.
watch(() => form.departure_date, (d) => {
    if (d && (!form.arrival_date || form.arrival_date < d)) form.arrival_date = d;
});

function copyFareToSeats() {
    form.adult_price = form.fare_price;
    form.child_price = form.fare_price;
}

// Live preview of the agent listing row.
const preview = computed(() => ({
    airline: props.options.airlines.find((a) => a.value === Number(form.airline_id)),
    bag: props.options.baggageTypes.find((b) => b.value === Number(form.baggage_type_id))?.label,
    dep: form.departure_date ? form.departure_date.split('-').reverse().join('-') : 'dd-mm-yyyy',
    arr: form.arrival_date ? form.arrival_date.split('-').reverse().join('-') : 'dd-mm-yyyy',
}));

const title = computed(() => (editing.value ? `Edit flight ${props.flight.flight_code}` : props.isDuplicate ? 'Duplicate flight' : 'Add flight'));

// Mirrors FlightRequest.
const price = (label) => [r.required(`Enter the ${label}.`), r.integer({ min: 0, max: 10000000 }, `${label[0].toUpperCase()}${label.slice(1)} must be a whole amount from 0 to 10,000,000.`)];
const minSeats = Math.max(1, Number(f.seats_confirmed ?? 0));
const { validate } = useValidation(form, {
    category_id: [r.required('Choose a main category.')],
    sector_id: [r.required('Choose a sector.')],
    airline_id: [r.required('Choose an airline.')],
    departure_date: [
        r.required('Choose the departure date.'),
        ...(editing.value ? [] : [r.dateAfterOrEqual(today, 'Departure date cannot be in the past.')]),
    ],
    arrival_date: [r.required('Choose the arrival date.'), r.dateAfterOrEqual(() => form.departure_date, 'Arrival date must be on or after the departure date.')],
    flight_code: [r.required('Enter the flight code, e.g. SV801 / SV800.'), r.max(60)],
    departure_time: [r.required('Choose the departure time.')],
    arrival_time: [r.required('Choose the arrival time.')],
    baggage_type_id: [r.required('Choose a bag type.')],
    ...(showPrices ? {
        fare_price: price('fare price'),
        adult_price: price('adult seat price'),
        child_price: price('child seat price'),
        infant_price: price('infant seat price'),
    } : {}),
    total_seats: [
        r.required('Enter how many seats are available.'),
        r.integer({ min: minSeats, max: 1000 }, minSeats > 1
            ? `Seats must be from ${minSeats} (already confirmed) to 1000.`
            : 'Seats must be a whole number from 1 to 1000.'),
    ],
    pnr: [r.max(30)],
});

function submit(publish = false) {
    form.publish = publish;
    if (!validate()) return;
    const options = { preserveScroll: true, onError: () => focusFirstError() };
    if (editing.value) form.put(route('admin.flights.update', props.flight.id), options);
    else form.post(route('admin.flights.store'), options);
}

const segmentClass = (active) => [
    'h-11 flex-1 rounded-xl border text-sm font-semibold transition',
    active ? 'border-secondary bg-secondary text-white shadow-sm' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300',
];
</script>

<template>
    <AdminLayout :title="title" :subtitle="isDuplicate ? 'Copied from an existing flight — set the new dates' : 'Fill in the group details agents will book'">
        <div v-if="isDuplicate" class="mx-auto mb-6 flex max-w-6xl items-center gap-3 rounded-2xl border border-tertiary-200 bg-tertiary-50 px-4 py-3 text-sm text-slate-700">
            <DocumentDuplicateIcon class="size-5 text-tertiary-700" /> Everything is copied except the dates and PNR.
        </div>

        <form class="mx-auto grid max-w-6xl gap-6 pb-24 xl:grid-cols-[1fr_320px]" novalidate @submit.prevent="submit(false)">
            <div class="space-y-6">
                <!-- Route -->
                <section class="card p-6">
                    <h2 class="flex items-center gap-2 font-semibold"><MapIcon class="size-5 text-tertiary-600" /> Route</h2>
                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        <Select v-model="form.category_id" label="Main category" :options="options.categories" placeholder="Select category" :error="form.errors.category_id" required />
                        <Select v-model="form.sector_id" label="Sub category (sector)" :options="sectorOptions" :placeholder="form.category_id ? 'Select sector' : 'Choose a category first'" :disabled="!form.category_id" :error="form.errors.sector_id" required />
                        <Select v-model="form.airline_id" label="Airline" :options="options.airlines" placeholder="Select airline" :error="form.errors.airline_id" required class="sm:col-span-2" />
                    </div>
                </section>

                <!-- Schedule -->
                <section class="card p-6">
                    <h2 class="flex items-center gap-2 font-semibold"><CalendarDaysIcon class="size-5 text-tertiary-600" /> Schedule</h2>
                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        <Input v-model="form.departure_date" type="date" label="Departure date" :error="form.errors.departure_date" required />
                        <Input v-model="form.arrival_date" type="date" :label="isReturn ? 'Arrival (return) date' : 'Arrival date'" :min="form.departure_date || undefined" :error="form.errors.arrival_date" required />
                        <Input
                            v-model="form.flight_code" label="Flight (code)" placeholder="SV801 / SV800" :error="form.errors.flight_code" required
                            class="sm:col-span-2 [&_input]:uppercase" hint="Write both flight numbers for return groups, e.g. 9P700 / 9P701."
                        />

                        <div class="sm:col-span-2">
                            <p class="mb-1.5 flex items-center gap-1.5 text-sm font-medium text-slate-700"><ClockIcon class="size-4" /> Time format</p>
                            <div class="flex max-w-sm gap-2">
                                <button type="button" :class="segmentClass(timeFormat === '12')" :aria-pressed="timeFormat === '12'" @click="setTimeFormat('12')">12 Hour</button>
                                <button type="button" :class="segmentClass(timeFormat === '24')" :aria-pressed="timeFormat === '24'" @click="setTimeFormat('24')">24 Hour</button>
                            </div>
                            <p class="mt-1.5 text-xs text-slate-500">Choosing a format fills default times ({{ DEFAULT_TIMES.departure }} → {{ DEFAULT_TIMES.arrival }}) if they're empty. Times are always saved in 24-hour.</p>
                        </div>
                        <TimeInput v-model="form.departure_time" :format="timeFormat" label="Departure time" :error="form.errors.departure_time" required />
                        <TimeInput v-model="form.arrival_time" :format="timeFormat" label="Arrival time" :error="form.errors.arrival_time" required />
                    </div>
                </section>

                <!-- Services -->
                <section class="card p-6">
                    <h2 class="flex items-center gap-2 font-semibold"><BriefcaseIcon class="size-5 text-tertiary-600" /> Baggage & meal</h2>
                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        <Select v-model="form.baggage_type_id" label="Bag type" :options="options.baggageTypes" placeholder="Select bag type" :error="form.errors.baggage_type_id" required />
                        <div>
                            <p class="mb-1.5 text-sm font-medium text-slate-700">Meal <span class="text-rose-500">*</span></p>
                            <div class="flex gap-2" role="radiogroup" aria-label="Meal">
                                <button type="button" role="radio" :aria-checked="form.meal" :class="segmentClass(form.meal)" @click="form.meal = true">Yes</button>
                                <button type="button" role="radio" :aria-checked="!form.meal" :class="segmentClass(!form.meal)" @click="form.meal = false">No</button>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Pricing -->
                <section v-if="showPrices" class="card p-6">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="flex items-center gap-2 font-semibold"><BanknotesIcon class="size-5 text-tertiary-600" /> Pricing (PKR)</h2>
                        <Button size="sm" variant="ghost" :disabled="!form.fare_price" @click="copyFareToSeats">Copy fare to adult & child</Button>
                    </div>
                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        <Input v-model="form.fare_price" type="number" min="0" step="1" label="Fare price" hint="Shown on the listing" :error="form.errors.fare_price" required class="sm:col-span-2" />
                        <Input v-model="form.adult_price" type="number" min="0" label="Adult seat price" :error="form.errors.adult_price" required />
                        <Input v-model="form.child_price" type="number" min="0" label="Child seat price" :error="form.errors.child_price" required />
                        <Input v-model="form.infant_price" type="number" min="0" label="Infant seat price" :error="form.errors.infant_price" required />
                    </div>
                </section>
                <section v-else class="card flex items-center gap-3 p-6 text-sm text-slate-500">
                    <BanknotesIcon class="size-5" /> Prices are hidden for your role and won't be changed.
                </section>

                <!-- Inventory -->
                <section class="card p-6">
                    <h2 class="flex items-center gap-2 font-semibold"><TicketIcon class="size-5 text-tertiary-600" /> Seats & PNR</h2>
                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        <Input
                            v-model="form.total_seats" type="number" min="1" label="Quantity available" :error="form.errors.total_seats" required
                            :hint="flight?.seats_confirmed ? `${flight.seats_confirmed} seats are already confirmed.` : 'Total seats in this group.'"
                        />
                        <Input v-model="form.pnr" label="PNR" placeholder="ABC123" :error="form.errors.pnr" class="[&_input]:uppercase" hint="Agents see it only after confirmation." />
                    </div>
                </section>
            </div>

            <!-- Preview -->
            <aside class="space-y-4 xl:sticky xl:top-28 xl:self-start">
                <div class="card overflow-hidden">
                    <div class="bg-secondary px-5 py-3">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-tertiary">Agent preview</p>
                        <p class="mt-0.5 truncate font-display font-semibold !text-white">{{ selectedSector?.label ?? 'Select a sector' }}</p>
                    </div>
                    <dl class="divide-y divide-slate-100 text-sm">
                        <div class="flex justify-between px-5 py-2.5"><dt class="text-slate-500">Airline</dt><dd class="font-medium text-slate-900">{{ preview.airline?.label ?? '—' }}</dd></div>
                        <div class="flex justify-between px-5 py-2.5"><dt class="text-slate-500">Date</dt><dd class="text-right font-medium text-slate-900">{{ preview.dep }}<br />{{ preview.arr }}</dd></div>
                        <div class="flex justify-between px-5 py-2.5"><dt class="text-slate-500">Flight</dt><dd class="font-medium text-slate-900">{{ form.flight_code || '—' }}</dd></div>
                        <div class="flex justify-between px-5 py-2.5"><dt class="text-slate-500">Time</dt><dd class="font-medium text-slate-900">{{ form.departure_time || '--:--' }} – {{ form.arrival_time || '--:--' }}</dd></div>
                        <div class="flex justify-between px-5 py-2.5"><dt class="text-slate-500">Bag</dt><dd class="font-medium text-slate-900">{{ preview.bag ?? '—' }}</dd></div>
                        <div class="flex justify-between px-5 py-2.5"><dt class="text-slate-500">Meal</dt><dd :class="['font-bold', form.meal ? 'text-emerald-600' : 'text-rose-600']">{{ form.meal ? 'YES' : 'NO' }}</dd></div>
                        <div v-if="showPrices" class="flex justify-between bg-tertiary-50 px-5 py-3"><dt class="text-slate-600">Fare</dt><dd class="font-display text-lg font-bold text-secondary">{{ formatPKR(form.fare_price || null) }}</dd></div>
                        <div class="flex justify-between px-5 py-2.5"><dt class="text-slate-500">Seats</dt><dd class="font-medium text-slate-900">{{ form.total_seats || '—' }}</dd></div>
                    </dl>
                </div>
                <p v-if="flight?.status" class="px-1 text-xs text-slate-500">Current status: <strong class="text-slate-700">{{ flight.status_label }}</strong></p>
            </aside>

            <!-- Actions -->
            <div class="fixed inset-x-0 bottom-0 z-20 border-t border-slate-200 bg-white/95 backdrop-blur lg:left-72">
                <div class="mx-auto flex max-w-6xl flex-col-reverse gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <Button variant="ghost" :href="route('admin.flights.index')"><ArrowLeftIcon class="size-4" /> Back to flights</Button>
                    <div class="flex flex-col-reverse gap-2 sm:flex-row">
                        <template v-if="editing">
                            <Button type="submit" :loading="form.processing">Save changes</Button>
                        </template>
                        <template v-else>
                            <Button type="submit" variant="secondary" :loading="form.processing && !form.publish">Save as draft</Button>
                            <Button v-if="can('flights.publish')" variant="gold" :loading="form.processing && form.publish" @click="submit(true)">
                                <PaperAirplaneIcon class="size-4 -rotate-45" /> Save & publish
                            </Button>
                        </template>
                    </div>
                </div>
            </div>
        </form>
    </AdminLayout>
</template>
