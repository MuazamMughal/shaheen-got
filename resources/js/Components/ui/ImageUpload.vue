<script setup>
/**
 * Image picker with preview. v-model holds the File to upload (or null).
 * `current` is the URL of the saved image; emits `remove` when the user
 * clears it (send remove_<field>=true to the server).
 */
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { PhotoIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const model = defineModel({ type: [File, null], default: null });

const props = defineProps({
    label: { type: String, default: null },
    current: { type: String, default: null },
    removed: { type: Boolean, default: false },
    error: { type: String, default: null },
    hint: { type: String, default: 'PNG, JPG or WEBP' },
    accept: { type: String, default: 'image/png,image/jpeg,image/webp' },
    aspect: { type: String, default: 'aspect-[16/9]' }, // preview box shape
    fit: { type: String, default: 'object-cover' },
    maxKb: { type: Number, default: 2048 }, // mirror the server's `max:` rule
});

const emit = defineEmits(['remove']);

const input = ref(null);
const dragging = ref(false);
const objectUrl = ref(null);
const localError = ref(null); // size/type problems caught before uploading
const shownError = computed(() => localError.value ?? props.error);

const sizeLabel = computed(() => (props.maxKb >= 1024 ? `${+(props.maxKb / 1024).toFixed(1)} MB` : `${props.maxKb} KB`));

// Does the file match the `accept` list (MIME types like image/png or extensions like .ico)?
function accepted(file) {
    const name = file.name.toLowerCase();
    return props.accept.split(',').map((a) => a.trim().toLowerCase()).filter(Boolean)
        .some((a) => (a.startsWith('.') ? name.endsWith(a) : a.endsWith('/*') ? file.type.startsWith(a.slice(0, -1)) : file.type === a));
}

const allowedLabel = computed(() => [...new Set(props.accept.split(',').map((a) => {
    a = a.trim().toLowerCase();
    if (a === 'image/x-icon' || a === 'image/vnd.microsoft.icon') return 'ICO';
    return (a.startsWith('.') ? a.slice(1) : a.split('/')[1] ?? a).replace('jpeg', 'JPG').replace('*', 'image').toUpperCase();
}))].join(', ').replace(/, ([^,]+)$/, ' or $1'));

watch(model, (file) => {
    if (objectUrl.value) URL.revokeObjectURL(objectUrl.value);
    objectUrl.value = file ? URL.createObjectURL(file) : null;
});
watch(() => props.current, () => (localError.value = null)); // another record opened
onBeforeUnmount(() => objectUrl.value && URL.revokeObjectURL(objectUrl.value));

const preview = computed(() => objectUrl.value ?? (props.removed ? null : props.current));

function pick(files) {
    const file = files?.[0];
    if (input.value) input.value.value = '';
    if (!file) return;
    if (!accepted(file)) {
        localError.value = `${props.label ?? 'Image'} must be a ${allowedLabel.value} file.`;
        return;
    }
    if (file.size > props.maxKb * 1024) {
        localError.value = `${props.label ?? 'Image'} must be ${sizeLabel.value} or smaller (this one is ${(file.size / 1048576).toFixed(1)} MB).`;
        return;
    }
    localError.value = null;
    model.value = file;
}

function clear() {
    localError.value = null;
    model.value = null;
    if (input.value) input.value.value = '';
    if (props.current) emit('remove');
}
</script>

<template>
    <div>
        <p v-if="label" class="mb-1.5 text-sm font-medium text-slate-700">{{ label }}</p>
        <div
            :class="['group relative overflow-hidden rounded-xl border-2 border-dashed transition', aspect,
                dragging ? 'border-tertiary bg-tertiary-50' : shownError ? 'border-rose-300' : 'border-slate-200 bg-slate-50 hover:border-secondary-300']"
            @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false"
            @drop.prevent="dragging = false; pick($event.dataTransfer.files)"
        >
            <img v-if="preview" :src="preview" alt="" :class="['absolute inset-0 size-full', fit, fit === 'object-contain' ? 'p-3' : '']" />
            <button
                type="button"
                class="absolute inset-0 flex flex-col items-center justify-center gap-1 text-slate-500 transition"
                :class="preview ? 'bg-secondary-900/0 opacity-0 hover:bg-secondary-900/50 hover:text-white hover:opacity-100 focus-visible:opacity-100' : ''"
                @click="input.click()"
            >
                <PhotoIcon class="size-7" />
                <span class="text-xs font-semibold">{{ preview ? 'Change image' : 'Click or drop an image' }}</span>
            </button>
            <button
                v-if="preview" type="button" aria-label="Remove image"
                class="absolute right-2 top-2 grid size-7 place-items-center rounded-full bg-white/90 text-slate-600 shadow hover:bg-rose-50 hover:text-rose-600"
                @click="clear"
            >
                <XMarkIcon class="size-4" />
            </button>
            <input ref="input" type="file" class="hidden" :accept="accept" @change="pick($event.target.files)" />
        </div>
        <p v-if="shownError" class="mt-1.5 text-xs font-medium text-rose-600" role="alert">{{ shownError }}</p>
        <p v-else-if="hint" class="mt-1.5 text-xs text-slate-500">{{ hint }}</p>
    </div>
</template>
