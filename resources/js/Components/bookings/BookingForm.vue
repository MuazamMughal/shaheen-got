<script setup>
/**
 * Booking form used by agents (create / edit) and admins (edit).
 * Counters drive the passenger rows; prices show a live breakdown.
 */
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { MinusIcon, PlusIcon, UserGroupIcon, IdentificationIcon, PaperAirplaneIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import Input from '@/Components/ui/Input.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import Button from '@/Components/ui/Button.vue';
import { formatPKR } from '@/Composables/useMoney';

const props = defineProps({
    flight: { type: Object, required: true },
    booking: { type: Object, default: null },
    paxRules: { type: Object, required: true },
    submitUrl: { type: String, required: true },
    method: { type: String, default: 'post' },
    backUrl: { type: String, required: true },
    countsLocked: { type: Boolean, default: false },
    showPrices: { type: Boolean, default: true },
    submitLabel: { type: String, default: 'Submit booking' },
});

const TYPES = [
    { key: 'adult', field: 'adults', label: 'Adults', hint: '12+ years' },
    { key: 'child', field: 'children', label: 'Children', hint: '2–11 years' },
    { key: 'infant', field: 'infants', label: 'Infants', hint: 'Under 2 · no seat' },
];

const blank = (type) => ({
    pax_type: type, title: props.paxRules[type].titles[0], given_name: '', surname: '',
    passport_no: '', dob: '', passport_issue_date: '', passport_expiry_date: '',
});

const b = props.booking;
const form = useForm({
    booker_name: b?.booker_name ?? '',
    booker_phone: b?.booker_phone ?? '',
    booker_email: b?.booker_email ?? '',
    adults: b?.adults ?? 1,
    children: b?.children ?? 0,
    infants: b?.infants ?? 0,
    agent_remarks: b?.agent_remarks ?? '',
    passengers: b?.passengers?.map((p) => ({ ...p, passport_issue_date: p.passport_issue_date ?? '' })) ?? [blank('adult')],
});

// Keep passenger rows in sync with the counters, preserving what was typed.
watch(() => [form.adults, form.children, form.infants], () => {
    const rows = [];
    for (const t of TYPES) {
        const existing = form.passengers.filter((p) => p.pax_type === t.key);
        for (let i = 0; i < form[t.field]; i++) rows.push(existing[i] ?? blank(t.key));
    }
    form.passengers = rows;
});

const seatsNeeded = computed(() => form.adults + form.children);
// When editing, this booking's own seats aren't counted against it (pending doesn't hold seats).
const seatsLeft = computed(() => props.flight.seats_available);
const overSeats = computed(() => seatsNeeded.value > seatsLeft.value);

function step(t, delta) {
    if (props.countsLocked) return;
    const next = form[t.field] + delta;
    const min = t.key === 'adult' ? 1 : 0;
    if (next < min) return;
    if (t.key === 'infant' && next > form.adults) return;           // one infant per adult
    if (t.key !== 'infant' && delta > 0 && seatsNeeded.value >= seatsLeft.value) return;
    if (t.key === 'adult' && delta < 0 && form.infants > next) form.infants = next;
    form[t.field] = next;
}

const price = (t) => props.flight[`${t}_price`];
const lineTotal = (t) => (price(t.key) ?? 0) * form[t.field];
const total = computed(() => TYPES.reduce((sum, t) => sum + lineTotal(t), 0));

const rowLabel = (p, i) => {
    const n = form.passengers.slice(0, i + 1).filter((x) => x.pax_type === p.pax_type).length;
    return `${{ adult: 'Adult', child: 'Child', infant: 'Infant' }[p.pax_type]} ${n}`;
};
const err = (i, field) => form.errors[`passengers.${i}.${field}`];

function submit() {
    form.submit(props.method, props.submitUrl, { preserveScroll: 'errors' });
}

const counterBtn = 'grid size-9 place-items-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:border-secondary hover:text-secondary disabled:cursor-not-allowed disabled:opacity-40';
const cellInput = 'h-10 w-full rounded-lg border bg-white px-2.5 text-sm text-slate-900 transition focus:outline-none focus:ring-4';
const cellOk = 'border-slate-200 focus:border-secondary-400 focus:ring-secondary-100';
const cellBad = 'border-rose-300 focus:border-rose-400 focus:ring-rose-100';
</script>

<template>
    <form class="space-y-6 pb-24" @submit.prevent="submit">
        <!-- Flight summary -->
        <section class="overflow-hidden rounded-2xl bg-secondary text-white">
            <div class="flex flex-wrap items-center justify-between gap-4 px-5 py-4 sm:px-6">
                <div class="flex items-center gap-3">
                    <span class="grid size-11 place-items-center rounded-xl bg-white/10 text-tertiary"><PaperAirplaneIcon class="size-6 -rotate-45" /></span>
                    <div>
                        <p class="font-display text-lg font-semibold tracking-wide">{{ flight.sector }}</p>
                        <p class="text-xs text-white/70">{{ flight.airline?.name }} · {{ flight.flight_codes?.join(' / ') }}</p>
                    </div>
                </div>
                <dl class="grid grid-cols-2 gap-x-6 gap-y-1 text-xs sm:grid-cols-4">
                    <div><dt class="text-white/60">Departure</dt><dd class="font-semibold">{{ flight.departure_date_label }}</dd></div>
                    <div><dt class="text-white/60">Return</dt><dd class="font-semibold">{{ flight.arrival_date_label }}</dd></div>
                    <div><dt class="text-white/60">Time</dt><dd class="font-semibold">{{ flight.departure_time }} – {{ flight.arrival_time }}</dd></div>
                    <div><dt class="text-white/60">Bag · Meal</dt><dd class="font-semibold">{{ flight.baggage }} · {{ flight.meal ? 'Yes' : 'No' }}</dd></div>
                </dl>
            </div>
        </section>

        <div v-if="form.errors.seats || form.errors.flight || form.errors.status" class="flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <ExclamationTriangleIcon class="size-5 shrink-0" />
            <p>{{ form.errors.seats ?? form.errors.flight ?? form.errors.status }}</p>
        </div>

        <!-- Passengers · Price/Seat · Total (as in the reference) -->
        <section class="grid gap-4 lg:grid-cols-3">
            <div class="card overflow-hidden">
                <h3 class="bg-secondary px-4 py-2.5 text-sm font-semibold text-white">Passengers</h3>
                <div class="divide-y divide-slate-100">
                    <div v-for="t in TYPES" :key="t.key" class="flex items-center justify-between gap-3 px-4 py-3">
                        <div>
                            <p class="text-sm font-medium text-slate-800">{{ t.label }}</p>
                            <p class="text-xs text-slate-500">{{ t.hint }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" :class="counterBtn" :disabled="countsLocked" :aria-label="`Fewer ${t.label}`" @click="step(t, -1)"><MinusIcon class="size-4" /></button>
                            <span class="w-8 text-center font-display text-lg font-semibold text-secondary" aria-live="polite">{{ form[t.field] }}</span>
                            <button type="button" :class="counterBtn" :disabled="countsLocked" :aria-label="`More ${t.label}`" @click="step(t, 1)"><PlusIcon class="size-4" /></button>
                        </div>
                    </div>
                    <div class="flex justify-between bg-slate-50 px-4 py-2.5 text-sm">
                        <span class="text-slate-500">Seats needed</span>
                        <span :class="['font-semibold', overSeats ? 'text-rose-600' : 'text-slate-900']">{{ seatsNeeded }} <span class="font-normal text-slate-400">/ {{ seatsLeft }} left</span></span>
                    </div>
                </div>
                <p v-if="countsLocked" class="border-t border-slate-100 px-4 py-2.5 text-xs text-slate-500">Passenger numbers can't change after payment is approved.</p>
            </div>

            <div v-if="showPrices" class="card overflow-hidden">
                <h3 class="bg-secondary px-4 py-2.5 text-sm font-semibold text-white">Price / Seat</h3>
                <dl class="divide-y divide-slate-100 text-sm">
                    <div v-for="t in TYPES" :key="t.key" class="flex justify-between px-4 py-3"><dt class="text-slate-500">{{ t.label.replace(/ren$|s$/, '') }}</dt><dd class="font-semibold text-slate-900">{{ formatPKR(price(t.key)) }}</dd></div>
                </dl>
            </div>

            <div v-if="showPrices" class="card overflow-hidden">
                <h3 class="bg-secondary px-4 py-2.5 text-sm font-semibold text-white">Total Price</h3>
                <dl class="divide-y divide-slate-100 text-sm">
                    <div v-for="t in TYPES" :key="t.key" class="flex justify-between px-4 py-3">
                        <dt class="text-slate-500">{{ t.label }} × {{ form[t.field] }}</dt>
                        <dd :class="['font-semibold', form[t.field] ? 'text-emerald-700' : 'text-slate-300']">{{ formatPKR(lineTotal(t)) }}</dd>
                    </div>
                    <div class="flex justify-between bg-tertiary-50 px-4 py-3">
                        <dt class="font-semibold text-slate-700">Total</dt>
                        <dd class="font-display text-lg font-bold text-secondary">{{ formatPKR(total) }}</dd>
                    </div>
                </dl>
            </div>
        </section>

        <!-- Booker -->
        <section class="card p-5 sm:p-6">
            <h3 class="flex items-center gap-2 font-semibold"><UserGroupIcon class="size-5 text-tertiary-600" /> Booker details</h3>
            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                <Input v-model="form.booker_name" label="Booker name" :error="form.errors.booker_name" required autocomplete="name" />
                <Input v-model="form.booker_phone" label="Booker phone" type="tel" :error="form.errors.booker_phone" required autocomplete="tel" />
                <Input v-model="form.booker_email" label="Booker email" type="email" :error="form.errors.booker_email" hint="Optional — receives the confirmation too." />
            </div>
        </section>

        <!-- Passenger details -->
        <section class="card overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-2 px-5 pt-5 sm:px-6">
                <h3 class="flex items-center gap-2 font-semibold"><IdentificationIcon class="size-5 text-tertiary-600" /> Passenger details</h3>
                <p class="text-xs text-slate-500">Names exactly as in the passport. Passport must be valid after {{ flight.arrival_date_label }}.</p>
            </div>
            <p v-if="form.errors.passengers" class="mx-5 mt-3 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700 sm:mx-6">{{ form.errors.passengers }}</p>

            <div class="mt-4 space-y-3 px-3 pb-5 sm:px-4">
                <fieldset
                    v-for="(p, i) in form.passengers" :key="i"
                    class="grid gap-3 rounded-xl border border-slate-200 bg-slate-50/50 p-3 sm:grid-cols-6 xl:grid-cols-[110px_90px_1fr_1fr_1fr_140px_140px_140px] xl:items-start"
                >
                    <legend class="sr-only">{{ rowLabel(p, i) }}</legend>
                    <div class="flex items-center gap-2 sm:col-span-6 xl:col-span-1 xl:pt-2.5">
                        <span class="rounded-md bg-secondary px-2 py-1 text-xs font-semibold text-white">{{ rowLabel(p, i) }}</span>
                        <span v-if="i === 0" class="text-[10px] font-semibold uppercase text-tertiary-700">Lead</span>
                    </div>
                    <label class="sm:col-span-1 xl:col-span-1">
                        <span class="mb-1 block text-xs font-medium text-slate-600 xl:sr-only">Title</span>
                        <select v-model="p.title" :class="[cellInput, err(i, 'title') ? cellBad : cellOk]" aria-label="Title">
                            <option v-for="t in paxRules[p.pax_type].titles" :key="t" :value="t">{{ t }}</option>
                        </select>
                    </label>
                    <label class="sm:col-span-2 xl:col-span-1">
                        <span class="mb-1 block text-xs font-medium text-slate-600 xl:sr-only">Given name</span>
                        <input v-model="p.given_name" :class="[cellInput, 'uppercase', err(i, 'given_name') ? cellBad : cellOk]" placeholder="Given name" aria-label="Given name" autocomplete="off" required />
                        <span v-if="err(i, 'given_name')" class="mt-1 block text-[11px] text-rose-600">{{ err(i, 'given_name') }}</span>
                    </label>
                    <label class="sm:col-span-3 xl:col-span-1">
                        <span class="mb-1 block text-xs font-medium text-slate-600 xl:sr-only">Surname</span>
                        <input v-model="p.surname" :class="[cellInput, 'uppercase', err(i, 'surname') ? cellBad : cellOk]" placeholder="Surname" aria-label="Surname" autocomplete="off" required />
                        <span v-if="err(i, 'surname')" class="mt-1 block text-[11px] text-rose-600">{{ err(i, 'surname') }}</span>
                    </label>
                    <label class="sm:col-span-2 xl:col-span-1">
                        <span class="mb-1 block text-xs font-medium text-slate-600 xl:sr-only">Passport #</span>
                        <input v-model="p.passport_no" :class="[cellInput, 'uppercase', err(i, 'passport_no') ? cellBad : cellOk]" placeholder="Passport #" aria-label="Passport number" autocomplete="off" required />
                        <span v-if="err(i, 'passport_no')" class="mt-1 block text-[11px] text-rose-600">{{ err(i, 'passport_no') }}</span>
                    </label>
                    <label class="sm:col-span-2 xl:col-span-1">
                        <span class="mb-1 block text-xs font-medium text-slate-600">Date of birth</span>
                        <input v-model="p.dob" type="date" :class="[cellInput, err(i, 'dob') ? cellBad : cellOk]" required />
                        <span v-if="err(i, 'dob')" class="mt-1 block text-[11px] text-rose-600">{{ err(i, 'dob') }}</span>
                        <span v-else class="mt-1 block text-[10px] text-slate-400">{{ paxRules[p.pax_type].age }}</span>
                    </label>
                    <label class="sm:col-span-2 xl:col-span-1">
                        <span class="mb-1 block text-xs font-medium text-slate-600">Passport issue</span>
                        <input v-model="p.passport_issue_date" type="date" :class="[cellInput, err(i, 'passport_issue_date') ? cellBad : cellOk]" />
                        <span v-if="err(i, 'passport_issue_date')" class="mt-1 block text-[11px] text-rose-600">{{ err(i, 'passport_issue_date') }}</span>
                    </label>
                    <label class="sm:col-span-2 xl:col-span-1">
                        <span class="mb-1 block text-xs font-medium text-slate-600">Passport expiry</span>
                        <input v-model="p.passport_expiry_date" type="date" :class="[cellInput, err(i, 'passport_expiry_date') ? cellBad : cellOk]" required />
                        <span v-if="err(i, 'passport_expiry_date')" class="mt-1 block text-[11px] text-rose-600">{{ err(i, 'passport_expiry_date') }}</span>
                    </label>
                </fieldset>
            </div>
        </section>

        <section class="card p-5 sm:p-6">
            <Textarea v-model="form.agent_remarks" label="Remarks (optional)" :rows="2" :error="form.errors.agent_remarks" placeholder="Anything our team should know" />
        </section>

        <div class="fixed inset-x-0 bottom-0 z-20 border-t border-slate-200 bg-white/95 backdrop-blur lg:left-72">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3 sm:px-6">
                <div class="hidden text-sm sm:block">
                    <span class="text-slate-500">{{ form.adults + form.children + form.infants }} passenger(s)</span>
                    <span v-if="showPrices" class="ml-3 font-display text-lg font-bold text-secondary">{{ formatPKR(total) }}</span>
                </div>
                <div class="flex flex-1 justify-end gap-2 sm:flex-none">
                    <Button variant="secondary" :href="backUrl">Cancel</Button>
                    <Button type="submit" :loading="form.processing" :disabled="overSeats">{{ submitLabel }}</Button>
                </div>
            </div>
        </div>
    </form>
</template>
