<script setup>
/**
 * Confirm dialog for agent status changes. `action` = { type, agent } where
 * type is approve | reject | suspend | reactivate. Reject/suspend need a reason.
 */
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import { rules as r, useValidation } from '@/Composables/useValidation';

const props = defineProps({ action: { type: Object, default: null } });
const emit = defineEmits(['close']);

const form = useForm({ reason: '' });
const { validate, reset: resetValidation } = useValidation(form, {
    reason: [r.required('Enter a reason.'), r.max(500, 'Keep the reason under 500 characters.')],
});
watch(() => props.action, () => { resetValidation(); form.reset(); form.clearErrors(); });

const config = computed(() => {
    const name = props.action?.agent?.agency_name ?? props.action?.agent?.name ?? '';
    return {
        approve: { title: 'Approve agent?', message: `${name} will be able to sign in and book group seats.`, confirm: 'Approve', variant: 'success' },
        reject: { title: 'Reject registration?', message: `${name} will not be able to sign in. Tell them why.`, confirm: 'Reject', variant: 'danger', reason: true },
        suspend: { title: 'Suspend agent?', message: `${name} will be signed out and blocked until reactivated.`, confirm: 'Suspend', variant: 'danger', reason: true },
        reactivate: { title: 'Reactivate agent?', message: `${name} will be able to sign in and book again.`, confirm: 'Reactivate', variant: 'success' },
    }[props.action?.type] ?? {};
});

// Reject/suspend need a reason: the button stays off until one is typed.
const blocked = computed(() => !!config.value.reason && !form.reason.trim());

function confirm() {
    if (config.value.reason && !validate()) return;
    form.patch(route(`admin.agents.${props.action.type}`, props.action.agent.id), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}
</script>

<template>
    <ConfirmDialog
        :show="!!action" :loading="form.processing" :title="config.title" :message="config.message"
        :confirm-text="config.confirm" :variant="config.variant" :disabled="blocked" @close="emit('close')" @confirm="confirm"
    >
        <Textarea
            v-if="config.reason" v-model="form.reason" label="Reason" :rows="3" :error="form.errors.reason" required maxlength="500" class="mt-4"
            placeholder="e.g. Incomplete agency details" hint="Shown to the agent when they try to sign in."
        />
    </ConfirmDialog>
</template>
