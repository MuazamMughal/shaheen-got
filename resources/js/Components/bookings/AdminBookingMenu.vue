<script setup>
/** "⋯" menu + status actions for one booking row (admin). */
import { Link } from '@inertiajs/vue3';
import { EyeIcon, PencilSquareIcon, ArrowDownTrayIcon, BanknotesIcon, CheckCircleIcon, XCircleIcon, NoSymbolIcon, QueueListIcon } from '@heroicons/vue/24/outline';
import RowActions from '@/Components/ui/RowActions.vue';
import { usePermissions } from '@/Composables/usePermissions';

defineProps({ booking: { type: Object, required: true }, showFlightLink: { type: Boolean, default: true } });
const emit = defineEmits(['action']);
const { can } = usePermissions();
const item = 'flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 focus:bg-slate-50 focus:outline-none';
const open = (s) => ['pending', 'payment_approved'].includes(s);
</script>

<template>
    <RowActions :label="`Actions for ${booking.booking_no}`">
        <Link :href="route('admin.bookings.show', booking.id)" role="menuitem" :class="item"><EyeIcon class="size-4" /> View</Link>
        <button v-if="can('bookings.approve_payment') && booking.status === 'pending'" type="button" role="menuitem" :class="item" @click="emit('action', { type: 'approve-payment', booking })"><BanknotesIcon class="size-4 text-sky-600" /> Approve payment</button>
        <button v-if="can('bookings.confirm') && booking.status === 'payment_approved'" type="button" role="menuitem" :class="item" @click="emit('action', { type: 'confirm', booking })"><CheckCircleIcon class="size-4 text-emerald-600" /> Confirm booking</button>
        <button v-if="can('bookings.reject') && open(booking.status)" type="button" role="menuitem" :class="[item, '!text-rose-600']" @click="emit('action', { type: 'reject', booking })"><XCircleIcon class="size-4" /> Reject</button>
        <button v-if="can('bookings.reject') && booking.status === 'confirmed'" type="button" role="menuitem" :class="[item, '!text-rose-600']" @click="emit('action', { type: 'cancel', booking })"><NoSymbolIcon class="size-4" /> Cancel</button>
        <Link v-if="can('bookings.edit') && !['rejected', 'cancelled'].includes(booking.status)" :href="route('admin.bookings.edit', booking.id)" role="menuitem" :class="item"><PencilSquareIcon class="size-4" /> Edit</Link>
        <Link v-if="showFlightLink" :href="route('admin.flights.bookings', booking.flight.id)" role="menuitem" :class="item"><QueueListIcon class="size-4" /> All bookings on this flight</Link>
        <a v-if="can('bookings.export')" :href="route('admin.bookings.pdf', booking.id)" role="menuitem" :class="item"><ArrowDownTrayIcon class="size-4" /> Export PDF</a>
    </RowActions>
</template>
