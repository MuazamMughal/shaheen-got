<script setup>
/** Read-only booking body shared by agent and admin detail pages. */
import { PaperAirplaneIcon, ClockIcon } from '@heroicons/vue/24/outline';
import { formatPKR } from '@/Composables/useMoney';

const props = defineProps({ booking: { type: Object, required: true } });
const fmt = (d) => (d ? d.split('-').reverse().join('-') : '—');
const hasPrices = () => props.booking.total_price !== undefined;
</script>

<template>
    <div class="space-y-6">
        <!-- Flight -->
        <section class="card overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 bg-secondary px-5 py-4 text-white">
                <div class="flex items-center gap-3">
                    <PaperAirplaneIcon class="size-6 -rotate-45 text-tertiary" />
                    <div>
                        <p class="font-display text-lg font-semibold tracking-wide">{{ booking.flight.sector }}</p>
                        <p class="text-xs text-white/70">{{ booking.flight.category }} · {{ booking.flight.airline?.name }}</p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-[11px] uppercase tracking-wider text-white/60">PNR</p>
                    <p class="font-display text-lg font-bold text-tertiary">{{ booking.flight.pnr || (booking.status === 'confirmed' ? '—' : 'After confirmation') }}</p>
                </div>
            </div>
            <dl class="grid grid-cols-2 gap-px bg-slate-100 text-sm sm:grid-cols-4">
                <div class="bg-white px-5 py-3"><dt class="text-xs text-slate-500">Flight</dt><dd class="font-semibold text-slate-900">{{ booking.flight.flight_code }}</dd></div>
                <div class="bg-white px-5 py-3"><dt class="text-xs text-slate-500">Departure</dt><dd class="font-semibold text-slate-900">{{ booking.flight.departure_date_label }}</dd></div>
                <div class="bg-white px-5 py-3"><dt class="text-xs text-slate-500">Return</dt><dd class="font-semibold text-slate-900">{{ booking.flight.arrival_date_label }}</dd></div>
                <div class="bg-white px-5 py-3"><dt class="text-xs text-slate-500">Time</dt><dd class="flex items-center gap-1 font-semibold text-slate-900"><ClockIcon class="size-4 text-slate-400" />{{ booking.flight.departure_time }} – {{ booking.flight.arrival_time }}</dd></div>
                <div class="bg-white px-5 py-3"><dt class="text-xs text-slate-500">Baggage</dt><dd class="font-semibold text-slate-900">{{ booking.flight.baggage }}</dd></div>
                <div class="bg-white px-5 py-3"><dt class="text-xs text-slate-500">Meal</dt><dd :class="['font-bold', booking.flight.meal ? 'text-emerald-600' : 'text-rose-600']">{{ booking.flight.meal ? 'YES' : 'NO' }}</dd></div>
                <div class="bg-white px-5 py-3"><dt class="text-xs text-slate-500">Booker</dt><dd class="font-semibold text-slate-900">{{ booking.booker_name }}</dd></div>
                <div class="bg-white px-5 py-3"><dt class="text-xs text-slate-500">Booker phone</dt><dd class="font-semibold text-slate-900">{{ booking.booker_phone }}</dd></div>
            </dl>
        </section>

        <!-- Passengers -->
        <section class="card overflow-hidden">
            <h3 class="border-b border-slate-100 px-5 py-4 font-semibold">Passengers ({{ booking.total_pax }})</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                        <tr><th class="px-5 py-2.5">#</th><th class="px-3 py-2.5">Name</th><th class="px-3 py-2.5">Type</th><th class="px-3 py-2.5">Passport</th><th class="px-3 py-2.5">DOB</th><th class="px-3 py-2.5">Expiry</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="(p, i) in booking.passengers" :key="p.id">
                            <td class="px-5 py-3 text-slate-400">{{ i + 1 }}</td>
                            <td class="whitespace-nowrap px-3 py-3 font-semibold text-slate-900">{{ p.title }} {{ p.given_name }} {{ p.surname }} <span v-if="p.is_lead" class="ml-1 rounded bg-tertiary-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-tertiary-700">Lead</span></td>
                            <td class="px-3 py-3 capitalize text-slate-600">{{ p.pax_type }}</td>
                            <td class="px-3 py-3 font-mono text-slate-700">{{ p.passport_no }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-slate-600">{{ fmt(p.dob) }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-slate-600">{{ fmt(p.passport_expiry_date) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Fare -->
        <section v-if="hasPrices()" class="card overflow-hidden">
            <h3 class="border-b border-slate-100 px-5 py-4 font-semibold">Fare</h3>
            <dl class="divide-y divide-slate-100 text-sm">
                <div class="flex justify-between px-5 py-3"><dt class="text-slate-500">Adult × {{ booking.adults }}</dt><dd class="font-semibold">{{ formatPKR(booking.adult_price * booking.adults) }}</dd></div>
                <div v-if="booking.children" class="flex justify-between px-5 py-3"><dt class="text-slate-500">Child × {{ booking.children }}</dt><dd class="font-semibold">{{ formatPKR(booking.child_price * booking.children) }}</dd></div>
                <div v-if="booking.infants" class="flex justify-between px-5 py-3"><dt class="text-slate-500">Infant × {{ booking.infants }}</dt><dd class="font-semibold">{{ formatPKR(booking.infant_price * booking.infants) }}</dd></div>
                <div class="flex justify-between bg-tertiary-50 px-5 py-3"><dt class="font-semibold">Total</dt><dd class="font-display text-lg font-bold text-secondary">{{ formatPKR(booking.total_price) }}</dd></div>
            </dl>
        </section>

        <section v-if="booking.agent_remarks" class="card p-5">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Agent remarks</p>
            <p class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ booking.agent_remarks }}</p>
        </section>
    </div>
</template>
