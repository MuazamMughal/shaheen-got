<script setup>
/**
 * Time picker that shows either 12-hour (h:mm AM/PM) or 24-hour (HH:mm)
 * controls. The v-model is ALWAYS "HH:MM" in 24-hour time, which is what
 * the server stores — the format only changes how it is entered.
 */
import { computed, useId } from 'vue';
import { ClockIcon } from '@heroicons/vue/24/outline';

const model = defineModel({ type: String, default: '' });

const props = defineProps({
    label: { type: String, default: null },
    format: { type: String, default: '24' }, // '12' | '24'
    error: { type: String, default: null },
    required: { type: Boolean, default: false },
});

const id = useId();
const pad = (n) => String(n).padStart(2, '0');

const parts = computed(() => {
    const m = /^(\d{1,2}):(\d{2})$/.exec(model.value ?? '');
    if (!m) return { h24: null, min: null };
    return { h24: Number(m[1]), min: Number(m[2]) };
});

const hours24 = Array.from({ length: 24 }, (_, i) => i);
const hours12 = Array.from({ length: 12 }, (_, i) => i + 1);
const minutes = Array.from({ length: 60 }, (_, i) => i);

const hour12 = computed(() => (parts.value.h24 === null ? '' : ((parts.value.h24 + 11) % 12) + 1));
const meridiem = computed(() => (parts.value.h24 === null ? 'AM' : parts.value.h24 >= 12 ? 'PM' : 'AM'));

function emit(h24, min) {
    model.value = `${pad(h24 ?? 0)}:${pad(min ?? 0)}`;
}

function set24(h) { emit(Number(h), parts.value.min ?? 0); }
function setMinute(m) { emit(parts.value.h24 ?? 0, Number(m)); }
function set12(h, mer = meridiem.value) {
    let h24 = Number(h) % 12;
    if (mer === 'PM') h24 += 12;
    emit(h24, parts.value.min ?? 0);
}
function setMeridiem(mer) {
    set12(hour12.value === '' ? 12 : hour12.value, mer);
}

const preview = computed(() => {
    if (parts.value.h24 === null) return null;
    return props.format === '12'
        ? `${pad(parts.value.h24)}:${pad(parts.value.min)} in 24-hour`
        : `${hour12.value}:${pad(parts.value.min)} ${meridiem.value}`;
});

const selectClass = (hasError) => [
    'h-11 rounded-xl border bg-white pl-3 pr-8 text-sm font-medium text-slate-900 transition focus:outline-none focus:ring-4',
    hasError ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-100' : 'border-slate-200 focus:border-secondary-400 focus:ring-secondary-100',
];
</script>

<template>
    <div>
        <label v-if="label" :for="id" class="mb-1.5 block text-sm font-medium text-slate-700">
            {{ label }}<span v-if="required" class="text-rose-500"> *</span>
        </label>
        <div class="flex items-center gap-2" role="group" :aria-label="label">
            <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-secondary-50 text-secondary"><ClockIcon class="size-5" /></span>

            <template v-if="format === '24'">
                <select :id="id" :class="selectClass(error)" :value="parts.h24 ?? ''" aria-label="Hour (00–23)" @change="set24($event.target.value)">
                    <option value="" disabled>HH</option>
                    <option v-for="h in hours24" :key="h" :value="h">{{ pad(h) }}</option>
                </select>
                <span class="font-bold text-slate-400">:</span>
                <select :class="selectClass(error)" :value="parts.min ?? ''" aria-label="Minute" @change="setMinute($event.target.value)">
                    <option value="" disabled>MM</option>
                    <option v-for="m in minutes" :key="m" :value="m">{{ pad(m) }}</option>
                </select>
            </template>

            <template v-else>
                <select :id="id" :class="selectClass(error)" :value="hour12" aria-label="Hour (1–12)" @change="set12($event.target.value)">
                    <option value="" disabled>hh</option>
                    <option v-for="h in hours12" :key="h" :value="h">{{ h }}</option>
                </select>
                <span class="font-bold text-slate-400">:</span>
                <select :class="selectClass(error)" :value="parts.min ?? ''" aria-label="Minute" @change="setMinute($event.target.value)">
                    <option value="" disabled>MM</option>
                    <option v-for="m in minutes" :key="m" :value="m">{{ pad(m) }}</option>
                </select>
                <div class="inline-flex overflow-hidden rounded-xl border border-slate-200" role="radiogroup" aria-label="AM or PM">
                    <button
                        v-for="mer in ['AM', 'PM']" :key="mer" type="button" role="radio" :aria-checked="meridiem === mer"
                        :class="['h-11 px-3 text-xs font-bold transition', meridiem === mer && parts.h24 !== null ? 'bg-secondary text-white' : 'bg-white text-slate-500 hover:bg-slate-50']"
                        @click="setMeridiem(mer)"
                    >{{ mer }}</button>
                </div>
            </template>
        </div>
        <p v-if="error" class="mt-1.5 text-xs font-medium text-rose-600">{{ error }}</p>
        <p v-else-if="preview" class="mt-1.5 text-xs text-slate-500">= {{ preview }}</p>
    </div>
</template>
