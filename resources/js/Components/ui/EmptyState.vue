<script setup>
defineProps({
    title: { type: String, default: 'Nothing here yet' },
    description: { type: String, default: null },
});
</script>

<template>
    <div class="flex flex-col items-center justify-center px-6 py-14 text-center">
        <div class="grid size-14 place-items-center rounded-2xl bg-secondary-50 text-secondary">
            <slot name="icon">
                <svg class="size-7" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                </svg>
            </slot>
        </div>
        <h3 class="mt-4 text-base font-semibold">{{ title }}</h3>
        <p v-if="description" class="mt-1 max-w-sm text-sm text-slate-500">{{ description }}</p>
        <div v-if="$slots.default" class="mt-5"><slot /></div>
    </div>
</template>
