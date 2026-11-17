<script setup>
import { computed, onMounted, ref, useAttrs, useId } from 'vue';

const model = defineModel({ type: [String, Number, null], default: '' });

const props = defineProps({
    label: { type: String, default: null },
    type: { type: String, default: 'text' },
    error: { type: String, default: null },
    hint: { type: String, default: null },
    required: { type: Boolean, default: false },
    autofocus: { type: Boolean, default: false },
    id: { type: String, default: null },
});

defineOptions({ inheritAttrs: false });

// `class`/`style` style the wrapper (for grid placement); everything else goes to the control.
const attrs = useAttrs();
const controlAttrs = computed(() => {
    const { class: _c, style: _s, ...rest } = attrs;
    return rest;
});

const input = ref(null);
const inputId = props.id ?? useId();

onMounted(() => {
    if (props.autofocus) input.value?.focus();
});

defineExpose({ focus: () => input.value?.focus() });
</script>

<template>
    <div :class="attrs.class" :style="attrs.style">
        <label v-if="label" :for="inputId" class="mb-1.5 block text-sm font-medium text-slate-700">
            {{ label }}<span v-if="required" class="text-rose-500"> *</span>
        </label>
        <div class="relative">
            <span v-if="$slots.icon" class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                <slot name="icon" />
            </span>
            <input
                :id="inputId"
                ref="input"
                v-model="model"
                :type="type"
                :required="required"
                :aria-invalid="!!error"
                :aria-describedby="error || hint ? `${inputId}-help` : undefined"
                v-bind="controlAttrs"
                :class="[
                    'block h-11 w-full rounded-xl border bg-white px-3.5 text-sm text-slate-900 placeholder:text-slate-400 transition',
                    'focus:outline-none focus:ring-4',
                    $slots.icon ? 'pl-10' : '',
                    error
                        ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-100'
                        : 'border-slate-200 focus:border-secondary-400 focus:ring-secondary-100',
                ]"
            />
        </div>
        <p v-if="error" :id="`${inputId}-help`" class="mt-1.5 text-xs font-medium text-rose-600">{{ error }}</p>
        <p v-else-if="hint" :id="`${inputId}-help`" class="mt-1.5 text-xs text-slate-500">{{ hint }}</p>
    </div>
</template>
