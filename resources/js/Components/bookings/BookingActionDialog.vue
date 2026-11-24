<script setup>
/**
 * Admin status change dialog. action = { type, booking } with type:
 * approve-payment (optional note), confirm (optional remarks),
 * reject (reason required), cancel (reason required).
 */
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import Input from '@/Components/ui/Input.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import { rules as r, useValidation } from '@/Composables/useValidation';
import { formatPKR } from '@/Composables/useMoney';

const props = defineProps({ action: { type: Object, default: null } });
const emit = defineEmits(['close']);

const form = useForm({ note: '', remarks: '', reason: '' });
const { validate, reset: resetValidation } = useValidation(form, {
    note: [r.max(255, 'Keep the reference under 255 characters.')],
    remarks: [r.max(1000, 'Keep the remarks under 1000 characters.')],
    reason: [r.required('Enter a reason.'), r.max(500, 'Keep the reason under 500 characters.')],
});
watch(() => props.action, () => { resetValidation(); form.reset(); form.clearErrors(); });

const b = computed(() => props.action?.booking ?? {});
const config = computed(() => ({
    'approve-payment': { title: 'Approve payment?', confirm: 'Approve payment', variant: 'success',
        message: `Mark ${b.value.booking_no} as paid${b.value.total_price !== undefined ? ` (${formatPKR(b.value.total_price)})` : ''}. You can confirm the seats next.` },
    confirm: { title: 'Confirm booking?', confirm: 'Confirm booking', variant: 'success',
        message: `${b.value.seats_needed} seat(s) on ${b.value.flight?.flight_code} will be confirmed for ${b.value.agent?.agency_name ?? 'the agent'}.` },
    reject: { title: 'Reject booking?', confirm: 'Reject', variant: 'danger', reason: true,
        message: `${b.value.booking_no} will be rejected. The agent will see your reason.` },
    cancel: { title: 'Cancel confirmed booking?', confirm: 'Cancel booking', variant: 'danger', reason: true,
        message: `${b.value.seats_needed} seat(s) will go back on sale.` },
}[props.action?.type] ?? {}));

const routeName = computed(() => ({ 'approve-payment': 'admin.bookings.approve-payment' }[props.action?.type] ?? `admin.bookings.${props.action?.type}`));

// Only the field shown for this action is checked; reject/cancel stay disabled until a reason is typed.
const fieldFor = computed(() => ({ 'approve-payment': 'note', confirm: 'remarks', reject: 'reason', cancel: 'reason' }[props.action?.type]));
const blocked = computed(() => !!config.value.reason && !form.reason.trim());

function submit() {
    if (fieldFor.value && !validate([fieldFor.value])) return;
    form.transform((d) => ({
        'approve-payment': { note: d.note }, confirm: { remarks: d.remarks }, reject: { reason: d.reason }, cancel: { reason: d.reason },
    }[props.action.type])).patch(route(routeName.value, b.value.id), { preserveScroll: true, onSuccess: () => emit('close') });
}

const firstError = computed(() => form.errors.status ?? form.errors.seats);
</script>

<template>
    <ConfirmDialog
        :show="!!action" :loading="form.processing" :title="config.title" :message="config.message"
        :confirm-text="config.confirm" :variant="config.variant" :disabled="blocked" @close="emit('close')" @confirm="submit"
    >
        <div class="mt-4 space-y-3">
            <Input v-if="action?.type === 'approve-payment'" v-model="form.note" label="Payment reference (optional)" placeholder="e.g. Meezan slip 4471 · PKR 370,000" :error="form.errors.note" maxlength="255" />
            <p v-if="action?.type === 'confirm' && !b.flight?.pnr" class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">This flight has no PNR yet. Add it on the flight so the agent sees it on their confirmation.</p>
            <Textarea v-if="action?.type === 'confirm'" v-model="form.remarks" label="Remarks (optional)" :rows="2" :error="form.errors.remarks" maxlength="1000" />
            <Textarea v-if="config.reason" v-model="form.reason" label="Reason" :rows="3" :error="form.errors.reason" required maxlength="500" placeholder="e.g. Seats taken by an earlier booking" />
            <p v-if="firstError" class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ firstError }}</p>
        </div>
    </ConfirmDialog>
</template>
