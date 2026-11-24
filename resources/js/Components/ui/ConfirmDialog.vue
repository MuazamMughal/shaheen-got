<script setup>
import Modal from './Modal.vue';
import Button from './Button.vue';

defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: 'Are you sure?' },
    message: { type: String, default: null },
    confirmText: { type: String, default: 'Confirm' },
    cancelText: { type: String, default: 'Cancel' },
    variant: { type: String, default: 'danger' }, // danger | primary | success
    loading: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false }, // e.g. until a required reason is typed
});

const emit = defineEmits(['confirm', 'close']);
</script>

<template>
    <Modal :show="show" max-width="md" :closeable="!loading" @close="emit('close')">
        <div class="flex gap-4">
            <div
                :class="[
                    'grid size-11 shrink-0 place-items-center rounded-full',
                    variant === 'danger' ? 'bg-rose-50 text-rose-600' : variant === 'success' ? 'bg-emerald-50 text-emerald-600' : 'bg-secondary-50 text-secondary',
                ]"
            >
                <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                </svg>
            </div>
            <div>
                <h3 class="text-base font-semibold">{{ title }}</h3>
                <p v-if="message" class="mt-1 text-sm text-slate-500">{{ message }}</p>
                <slot />
            </div>
        </div>
        <template #footer>
            <Button variant="secondary" :disabled="loading" @click="emit('close')">{{ cancelText }}</Button>
            <Button :variant="variant" :loading="loading" :disabled="disabled" @click="emit('confirm')">{{ confirmText }}</Button>
        </template>
    </Modal>
</template>
