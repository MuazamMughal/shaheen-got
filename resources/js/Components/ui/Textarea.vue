<script setup>
import { computed, useAttrs, useId } from 'vue';

const model = defineModel({ type: [String, null], default: '' });

const props = defineProps({
    label: { type: String, default: null },
    error: { type: String, default: null },
    hint: { type: String, default: null },
    rows: { type: Number, default: 3 },
    required: { type: Boolean, default: false },
});

defineOptions({ inheritAttrs: false });

const attrs = useAttrs();
const controlAttrs = computed(() => {
    const { class: _c, style: _s, ...rest } = attrs;
    return rest;
});
const id = useId();
</script>

<template>
    <div :class="attrs.class" :style="attrs.style">
        <label v-if="label" :for="id" class="mb-1.5 block text-sm font-medium text-slate-700">
            {{ label }}<span v-if="required" class="text-rose-500"> *</span>
        </label>
        <textarea
            :id="id" v-model="model" :rows="rows" :required="required" :aria-invalid="!!error" v-bind="controlAttrs"
            :class="[
                'block w-full rounded-xl border bg-white px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 transition focus:outline-none focus:ring-4',
                error ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-100' : 'border-slate-200 focus:border-secondary-400 focus:ring-secondary-100',
            ]"
        />
        <p v-if="error" class="mt-1.5 text-xs font-medium text-rose-600">{{ error }}</p>
        <p v-else-if="hint" class="mt-1.5 text-xs text-slate-500">{{ hint }}</p>
    </div>
</template>
