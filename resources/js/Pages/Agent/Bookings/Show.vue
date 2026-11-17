<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { ArrowLeftIcon, ArrowDownTrayIcon, PencilSquareIcon, XCircleIcon, CheckCircleIcon, ClockIcon, BanknotesIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import AgentLayout from '@/Layouts/AgentLayout.vue';
import Button from '@/Components/ui/Button.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import BookingStatusBadge from '@/Components/bookings/BookingStatusBadge.vue';
import BookingDetails from '@/Components/bookings/BookingDetails.vue';
import BookingTimeline from '@/Components/bookings/BookingTimeline.vue';
import WhatsAppButton from '@/Components/WhatsAppButton.vue';

const props = defineProps({
    booking: { type: Object, required: true },
    whatsappText: { type: String, required: true },
    can: { type: Object, required: true },
});

const adminWhatsapp = computed(() => usePage().props.settings?.contact?.whatsapp);

// What happens next, in plain words.
const steps = computed(() => {
    const s = props.booking.status;
    return [
        { label: 'Requested', done: true, icon: ClockIcon },
        { label: 'Payment approved', done: ['payment_approved', 'confirmed'].includes(s), icon: BanknotesIcon },
        { label: 'Confirmed', done: s === 'confirmed', icon: CheckCircleIcon },
    ];
});
const closed = computed(() => ['rejected', 'cancelled'].includes(props.booking.status));

const cancelling = ref(false);
const busy = ref(false);
function cancel() {
    busy.value = true;
    router.patch(route('agent.bookings.cancel', props.booking.id), {}, { onFinish: () => { busy.value = false; cancelling.value = false; } });
}
</script>

<template>
    <AgentLayout :title="`Booking ${booking.booking_no}`">
        <div class="mx-auto max-w-6xl">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <Button variant="ghost" size="sm" :href="route('agent.bookings.index')"><ArrowLeftIcon class="size-4" /> My bookings</Button>
                    <div class="mt-2 flex flex-wrap items-center gap-3">
                        <h2 class="font-display text-2xl font-semibold">{{ booking.booking_no }}</h2>
                        <BookingStatusBadge :booking="booking" with-payment />
                    </div>
                    <p class="mt-1 text-sm text-slate-500">Booked {{ booking.created_at_time }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button variant="secondary" :href="route('agent.bookings.pdf', booking.id)" as="a"><ArrowDownTrayIcon class="size-4" /> PDF</Button>
                    <Button v-if="can.edit" variant="secondary" :href="route('agent.bookings.edit', booking.id)"><PencilSquareIcon class="size-4" /> Edit</Button>
                    <Button v-if="can.cancel" variant="secondary" class="!text-rose-600" @click="cancelling = true"><XCircleIcon class="size-4" /> Cancel</Button>
                </div>
            </div>

            <!-- Progress / next step -->
            <div v-if="!closed" class="card mb-6 p-5">
                <ol class="grid grid-cols-3 gap-2">
                    <li v-for="(st, i) in steps" :key="st.label" class="flex flex-col items-center text-center">
                        <span :class="['grid size-10 place-items-center rounded-full', st.done ? 'bg-secondary text-tertiary' : 'bg-slate-100 text-slate-400']"><component :is="st.icon" class="size-5" /></span>
                        <span :class="['mt-2 text-xs font-semibold sm:text-sm', st.done ? 'text-slate-900' : 'text-slate-400']">{{ st.label }}</span>
                    </li>
                </ol>
                <div v-if="booking.status === 'pending'" class="mt-5 flex flex-col items-center gap-3 rounded-xl border border-tertiary-200 bg-tertiary-50 p-4 text-center sm:flex-row sm:text-left">
                    <p class="flex-1 text-sm text-slate-700"><strong>Next step:</strong> send your payment proof to our team on WhatsApp. We'll approve the payment and confirm your seats.</p>
                    <WhatsAppButton :number="adminWhatsapp" :text="whatsappText" label="Send payment proof" />
                    <p v-if="!adminWhatsapp" class="text-xs text-slate-500">WhatsApp number not set yet — please contact our office.</p>
                </div>
                <p v-else-if="booking.status === 'payment_approved'" class="mt-5 rounded-xl bg-sky-50 p-4 text-center text-sm text-sky-800">Payment received. Our team is confirming your seats.</p>
                <p v-else class="mt-5 rounded-xl bg-emerald-50 p-4 text-center text-sm text-emerald-800">Your seats are confirmed. PNR: <strong>{{ booking.flight.pnr ?? '—' }}</strong></p>
            </div>
            <div v-else class="mb-6 flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                <XMarkIcon class="size-5 shrink-0" />
                <p>This booking was <strong>{{ booking.status_label.toLowerCase() }}</strong>.<template v-if="booking.reject_reason || booking.cancel_reason"> Reason: {{ booking.reject_reason ?? booking.cancel_reason }}</template></p>
            </div>

            <div class="grid grid-cols-[minmax(0,1fr)] gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
                <BookingDetails :booking="booking" />
                <aside><BookingTimeline :logs="booking.logs" /></aside>
            </div>
        </div>

        <ConfirmDialog
            :show="cancelling" :loading="busy" title="Cancel this booking?" confirm-text="Cancel booking" cancel-text="Keep it"
            :message="`${booking.booking_no} will be cancelled. This can't be undone.`" @close="cancelling = false" @confirm="cancel"
        />
    </AgentLayout>
</template>
