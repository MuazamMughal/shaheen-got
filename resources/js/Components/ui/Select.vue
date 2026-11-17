<script setup>
import { computed, useAttrs, useId } from 'vue';

const model = defineModel({ type: [String, Number, null], default: '' });

const props = defineProps({
    label: { type: String, default: null },
    options: { type: Array, default: () => [] }, // [{ value, label }] or plain strings
    placeholder: { type: String, default: null },
    error: { type: String, default: null },
    required: { type: Boolean, default: false },
    id: { type: String, default: null },
});

defineOptions({ inheritAttrs: false });

// `class`/`style` style the wrapper (for grid placement); everything else goes to the control.
const attrs = useAttrs();
const controlAttrs = computed(() => {
    const { class: _c, style: _s, ...rest } = attrs;
    return rest;
});

const selectId = props.id ?? useId();
const normalize = (o) => (typeof o === 'object' ? o : { value: o, label: o });
</script>

<template>
    <div :class="attrs.class" :style="attrs.style">
        <label v-if="label" :for="selectId" class="mb-1.5 block text-sm font-medium text-slate-700">
            {{ label }}<span v-if="required" class="text-rose-500"> *</span>
        </label>
        <select
            :id="selectId"
            v-model="model"
            :required="required"
            :aria-invalid="!!error"
            v-bind="controlAttrs"
            :class="[
                'block h-11 w-full rounded-xl border bg-white pl-3.5 pr-10 text-sm text-slate-900 transition focus:outline-none focus:ring-4',
                error
                    ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-100'
                    : 'border-slate-200 focus:border-secondary-400 focus:ring-secondary-100',
            ]"
        >
            <option v-if="placeholder" value="" disabled>{{ placeholder }}</option>
            <option v-for="opt in options.map(normalize)" :key="opt.value" :value="opt.value">
                {{ opt.label }}
            </option>
        </select>
        <p v-if="error" class="mt-1.5 text-xs font-medium text-rose-600">{{ error }}</p>
    </div>
</template>
