<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import {
    ArrowLeftIcon, ArrowDownTrayIcon, PencilSquareIcon, BanknotesIcon, CheckCircleIcon, XCircleIcon, NoSymbolIcon,
    TrashIcon, ExclamationTriangleIcon, BuildingOffice2Icon, QueueListIcon, LockClosedIcon,
} from '@heroicons/vue/24/outline';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from '@/Components/ui/Button.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import BookingStatusBadge from '@/Components/bookings/BookingStatusBadge.vue';
import BookingDetails from '@/Components/bookings/BookingDetails.vue';
import BookingTimeline from '@/Components/bookings/BookingTimeline.vue';
import BookingActionDialog from '@/Components/bookings/BookingActionDialog.vue';
import WhatsAppButton from '@/Components/WhatsAppButton.vue';
import { formatPKR } from '@/Composables/useMoney';

const props = defineProps({
    booking: { type: Object, required: true },
    whatsappText: { type: String, required: true },
    competing: { type: Array, default: () => [] },
    can: { type: Object, required: true },
});

const action = ref(null);
const act = (type) => (action.value = { type, booking: props.booking });

const deleting = ref(false);
const busy = ref(false);
function destroy() {
    busy.value = true;
    router.delete(route('admin.bookings.destroy', props.booking.id), { onFinish: () => { busy.value = false; deleting.value = false; } });
}
</script>

<template>
    <AdminLayout :title="`Booking ${booking.booking_no}`" :subtitle="booking.agent?.agency_name">
        <template #actions>
            <Button variant="ghost" :href="route('admin.bookings.index')" class="hidden sm:inline-flex"><ArrowLeftIcon class="size-4" /> All bookings</Button>
        </template>

        <div class="mx-auto max-w-6xl">
            <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="font-display text-2xl font-semibold">{{ booking.booking_no }}</h2>
                        <BookingStatusBadge :booking="booking" with-payment />
                    </div>
                    <p class="mt-1 text-sm text-slate-500">Booked {{ booking.created_at_time }} · {{ booking.total_pax }} passenger(s) · {{ booking.seats_needed }} seat(s)</p>
                </div>

                <!-- Main workflow: Approve payment → Confirm -->
                <div class="flex flex-wrap gap-2">
                    <Button v-if="can.approve_payment" variant="primary" class="!bg-sky-600 hover:!bg-sky-700" @click="act('approve-payment')"><BanknotesIcon class="size-4" /> Approve payment</Button>
                    <Button v-if="can.confirm" variant="success" @click="act('confirm')"><CheckCircleIcon class="size-4" /> Confirm booking</Button>
                    <Button v-else-if="can.confirm_waiting_payment" variant="success" disabled title="Approve the payment first"><LockClosedIcon class="size-4" /> Confirm booking</Button>
                    <Button v-if="can.reject" variant="secondary" class="!text-rose-600" @click="act('reject')"><XCircleIcon class="size-4" /> Reject</Button>
                    <Button v-if="can.cancel" variant="secondary" class="!text-rose-600" @click="act('cancel')"><NoSymbolIcon class="size-4" /> Cancel</Button>
                </div>
            </div>

            <div v-if="booking.exceeds_seats" class="mb-6 flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                <ExclamationTriangleIcon class="size-5 shrink-0" />
                <p>Only <strong>{{ booking.flight.seats_available }}</strong> seat(s) are left on this flight, but this booking needs <strong>{{ booking.seats_needed }}</strong>. It can't be confirmed — reject it or add seats to the flight.</p>
            </div>
            <div v-if="booking.reject_reason || booking.cancel_reason" class="mb-6 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                <strong>{{ booking.status_label }}:</strong> {{ booking.reject_reason ?? booking.cancel_reason }}
            </div>

            <div class="grid grid-cols-[minmax(0,1fr)] gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
                <BookingDetails :booking="booking" />

                <aside class="space-y-6">
                    <!-- Agency -->
                    <section class="card p-5">
                        <div class="flex items-center gap-3">
                            <span class="grid size-10 place-items-center rounded-xl bg-secondary text-tertiary"><BuildingOffice2Icon class="size-5" /></span>
                            <div class="min-w-0">
                                <Link :href="route('admin.agents.show', booking.agent.id)" class="block truncate font-semibold text-slate-900 hover:text-secondary">{{ booking.agent.agency_name ?? booking.agent.name }}</Link>
                                <p class="truncate text-xs text-slate-500">{{ booking.agent.name }} · {{ booking.agent.phone }}</p>
                            </div>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <WhatsAppButton :number="booking.agent.whatsapp" :text="whatsappText" size="sm" label="WhatsApp agent" />
                            <a :href="`mailto:${booking.agent.email}`" class="inline-flex h-8 items-center rounded-xl px-3 text-xs font-semibold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50">Email</a>
                        </div>
                    </section>

                    <!-- Payment -->
                    <section class="card p-5 text-sm">
                        <h3 class="font-semibold">Payment</h3>
                        <dl class="mt-3 space-y-2">
                            <div class="flex justify-between"><dt class="text-slate-500">Status</dt><dd :class="['font-semibold', booking.payment_status === 'approved' ? 'text-emerald-600' : 'text-amber-600']">{{ booking.payment_status === 'approved' ? 'Approved' : 'Awaiting payment' }}</dd></div>
                            <div v-if="booking.total_price !== undefined" class="flex justify-between"><dt class="text-slate-500">Amount</dt><dd class="font-semibold">{{ formatPKR(booking.total_price) }}</dd></div>
                            <div v-if="booking.payment_approved_at" class="flex justify-between"><dt class="text-slate-500">Approved</dt><dd>{{ booking.payment_approved_at }}</dd></div>
                            <div v-if="booking.payment_note"><dt class="text-slate-500">Reference</dt><dd class="mt-0.5 rounded-lg bg-slate-50 px-3 py-2">{{ booking.payment_note }}</dd></div>
                        </dl>
                    </section>

                    <!-- Competing requests -->
                    <section v-if="competing.length" class="card p-5">
                        <h3 class="font-semibold">Other open requests on this flight</h3>
                        <p class="mt-1 text-xs text-slate-500">{{ booking.flight.seats_available }} seat(s) left. Confirm in the order you choose; the rest can be rejected.</p>
                        <ul class="mt-3 divide-y divide-slate-100 text-sm">
                            <li v-for="c in competing" :key="c.id" class="flex items-center justify-between gap-2 py-2">
                                <Link :href="route('admin.bookings.show', c.id)" class="min-w-0">
                                    <span class="block font-semibold text-secondary">{{ c.booking_no }}</span>
                                    <span class="block truncate text-xs text-slate-500">{{ c.agent?.agency_name }} · {{ c.seats_needed }} seat(s)</span>
                                </Link>
                                <BookingStatusBadge :booking="c" />
                            </li>
                        </ul>
                        <Button size="sm" variant="ghost" block class="mt-2" :href="route('admin.flights.bookings', booking.flight.id)"><QueueListIcon class="size-4" /> Open flight view</Button>
                    </section>

                    <BookingTimeline :logs="booking.logs" />

                    <div class="grid gap-2">
                        <Button v-if="can.export" variant="secondary" as="a" :href="route('admin.bookings.pdf', booking.id)"><ArrowDownTrayIcon class="size-4" /> Export PDF</Button>
                        <Button v-if="can.edit" variant="secondary" :href="route('admin.bookings.edit', booking.id)"><PencilSquareIcon class="size-4" /> Edit details</Button>
                        <Button v-if="can.delete" variant="ghost" class="!text-rose-600" @click="deleting = true"><TrashIcon class="size-4" /> Delete booking</Button>
                    </div>
                </aside>
            </div>
        </div>

        <BookingActionDialog :action="action" @close="action = null" />
        <ConfirmDialog
            :show="deleting" :loading="busy" title="Delete booking?" confirm-text="Delete"
            :message="`${booking.booking_no} will be removed from the lists.`" @close="deleting = false" @confirm="destroy"
        />
    </AdminLayout>
</template>
