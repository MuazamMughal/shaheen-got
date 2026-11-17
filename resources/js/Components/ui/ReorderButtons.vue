<script setup>
import { ChevronUpIcon, ChevronDownIcon } from '@heroicons/vue/20/solid';

defineProps({
    first: { type: Boolean, default: false },
    last: { type: Boolean, default: false },
    label: { type: String, default: 'item' },
});
const emit = defineEmits(['up', 'down']);
</script>

<template>
    <div class="inline-flex flex-col">
        <button type="button" :disabled="first" :aria-label="`Move ${label} up`" class="rounded p-0.5 text-slate-400 hover:bg-slate-100 hover:text-secondary disabled:opacity-25" @click="emit('up')">
            <ChevronUpIcon class="size-4" />
        </button>
        <button type="button" :disabled="last" :aria-label="`Move ${label} down`" class="rounded p-0.5 text-slate-400 hover:bg-slate-100 hover:text-secondary disabled:opacity-25" @click="emit('down')">
            <ChevronDownIcon class="size-4" />
        </button>
    </div>
</template>
