<script setup>
/** One sector's flights, styled like the reference listing (navy header, gold rows). */
import { computed } from 'vue';
import { PaperAirplaneIcon, ClockIcon } from '@heroicons/vue/24/solid';
import { formatPKR } from '@/Composables/useMoney';

const props = defineProps({
    sector: { type: Object, required: true },
    flights: { type: Array, required: true },
});
const emit = defineEmits(['book']);

const airlines = computed(() => {
    const map = new Map();
    props.flights.forEach((f) => map.set(f.airline.id, f.airline));
    return [...map.values()];
});
</script>

<template>
    <section class="overflow-hidden rounded-2xl border border-slate-200/70 bg-white shadow-sm">
        <!-- Sector heading: airline logo(s) · plane · sector name -->
        <header class="flex flex-wrap items-center justify-center gap-x-8 gap-y-3 px-5 py-4 sm:justify-between">
            <div class="flex items-center gap-3">
                <template v-for="a in airlines" :key="a.id">
                    <img v-if="a.logo_url" :src="a.logo_url" :alt="a.name" class="h-8 max-w-28 object-contain" />
                    <span v-else class="rounded-lg bg-secondary-50 px-2.5 py-1 text-sm font-bold text-secondary">{{ a.name }}</span>
                </template>
            </div>
            <div class="flex items-center gap-3">
                <PaperAirplaneIcon class="size-6 -rotate-12 text-secondary" />
                <h3 class="font-display text-lg font-bold tracking-wide text-secondary sm:text-xl">{{ sector.name }}</h3>
            </div>
        </header>

        <!-- Desktop table -->
        <div class="hidden overflow-x-auto xl:block">
            <table class="min-w-full text-center text-sm">
                <thead class="bg-secondary text-white">
                    <tr>
                        <th v-for="h in ['Date', 'Flight', 'Time', 'Bag', 'Meal', 'Fare', '']" :key="h" scope="col" class="px-4 py-3 text-xs font-semibold uppercase tracking-wider">{{ h }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-tertiary-200">
                    <tr v-for="f in flights" :key="f.id" class="bg-tertiary-100/70 transition hover:bg-tertiary-200/60">
                        <td class="whitespace-nowrap px-4 py-3 font-semibold text-slate-900">
                            <p class="flex items-center justify-center gap-2"><PaperAirplaneIcon class="size-4 -rotate-12 text-secondary" /> {{ f.departure_date_label }}</p>
                            <p class="mt-0.5 flex items-center justify-center gap-2 text-slate-700"><PaperAirplaneIcon class="size-4 rotate-[200deg] text-secondary" /> {{ f.arrival_date_label }}</p>
                        </td>
                        <td class="px-4 py-3 font-semibold text-slate-900">
                            <p v-for="code in f.flight_codes" :key="code">{{ code }}</p>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 font-semibold text-slate-900">
                            <span class="inline-flex items-center gap-1.5">{{ f.departure_time }} – {{ f.arrival_time }} <ClockIcon class="size-4 text-secondary" /></span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 font-semibold text-slate-900">{{ f.baggage }}</td>
                        <td :class="['px-4 py-3 font-bold', f.meal ? 'text-emerald-600' : 'text-rose-600']">{{ f.meal ? 'YES' : 'NO' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 font-display text-base font-bold text-secondary">{{ formatPKR(f.fare_price) }}</td>
                        <td class="px-4 py-3">
                            <p class="text-[10px] font-semibold text-slate-600">( GRP# {{ f.id }} )</p>
                            <button type="button" class="mt-1 inline-flex items-center gap-1.5 rounded-lg bg-secondary px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-secondary-700 focus:outline-none focus-visible:ring-4 focus-visible:ring-secondary-200" @click="emit('book', f)">
                                Book Now <span class="rounded bg-tertiary px-1.5 text-[10px] text-secondary-900">{{ f.seats_available }}</span>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Mobile cards -->
        <ul class="divide-y divide-tertiary-200 xl:hidden">
            <li v-for="f in flights" :key="f.id" class="bg-tertiary-100/70 p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">{{ f.departure_date_label }} → {{ f.arrival_date_label }}</p>
                        <p class="mt-0.5 text-xs text-slate-600">{{ f.flight_codes.join(' / ') }} · {{ f.departure_time }}–{{ f.arrival_time }}</p>
                    </div>
                    <p class="whitespace-nowrap font-display font-bold text-secondary">{{ formatPKR(f.fare_price) }}</p>
                </div>
                <div class="mt-3 flex items-center justify-between gap-3">
                    <p class="text-xs text-slate-700">
                        {{ f.baggage }} · Meal <strong :class="f.meal ? 'text-emerald-600' : 'text-rose-600'">{{ f.meal ? 'YES' : 'NO' }}</strong>
                    </p>
                    <button type="button" class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-secondary px-3.5 py-2 text-xs font-semibold text-white" @click="emit('book', f)">
                        Book Now <span class="rounded bg-tertiary px-1.5 text-[10px] text-secondary-900">{{ f.seats_available }}</span>
                    </button>
                </div>
            </li>
        </ul>
    </section>
</template>
