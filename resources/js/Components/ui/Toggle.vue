<script setup>
const model = defineModel({ type: Boolean, default: false });

defineProps({
    label: { type: String, default: null },
    description: { type: String, default: null },
    disabled: { type: Boolean, default: false },
    srLabel: { type: String, default: null }, // accessible name when no visible label
});
</script>

<template>
    <label :class="['inline-flex items-center gap-3', disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer']">
        <span class="relative inline-flex shrink-0">
            <input v-model="model" type="checkbox" class="peer sr-only" :disabled="disabled" :aria-label="srLabel ?? undefined" />
            <span class="h-6 w-11 rounded-full bg-slate-200 transition peer-checked:bg-secondary peer-focus-visible:ring-4 peer-focus-visible:ring-secondary-100" />
            <span class="absolute left-0.5 top-0.5 size-5 rounded-full bg-white shadow transition peer-checked:translate-x-5 peer-checked:bg-tertiary" />
        </span>
        <span v-if="label || description" class="leading-tight">
            <span v-if="label" class="block text-sm font-medium text-slate-800">{{ label }}</span>
            <span v-if="description" class="block text-xs text-slate-500">{{ description }}</span>
        </span>
    </label>
</template>
