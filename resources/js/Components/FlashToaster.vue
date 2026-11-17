<script setup>
import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const toasts = ref([]);
let nextId = 1;

function push(type, message) {
    if (!message) return;
    const id = nextId++;
    toasts.value.push({ id, type, message });
    setTimeout(() => dismiss(id), 4500);
}

function dismiss(id) {
    toasts.value = toasts.value.filter((t) => t.id !== id);
}

watch(
    () => page.props.flash,
    (flash) => {
        push('success', flash?.success);
        push('error', flash?.error);
    },
    { immediate: true, deep: true },
);
</script>

<template>
    <div class="pointer-events-none fixed inset-x-0 top-4 z-[60] flex flex-col items-center gap-2 px-4 sm:items-end sm:pr-6" aria-live="polite">
        <TransitionGroup
            enter-active-class="transition duration-200 ease-out" enter-from-class="translate-y-2 opacity-0"
            leave-active-class="transition duration-150 ease-in" leave-to-class="opacity-0"
        >
            <div
                v-for="t in toasts" :key="t.id"
                :class="['pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border bg-white px-4 py-3 shadow-lg',
                    t.type === 'success' ? 'border-emerald-200' : 'border-rose-200']"
            >
                <span :class="['mt-1.5 size-2 shrink-0 rounded-full', t.type === 'success' ? 'bg-emerald-500' : 'bg-rose-500']" />
                <p class="flex-1 text-sm text-slate-700">{{ t.message }}</p>
                <button type="button" class="text-slate-400 hover:text-slate-600" aria-label="Dismiss" @click="dismiss(t.id)">✕</button>
            </div>
        </TransitionGroup>
    </div>
</template>
