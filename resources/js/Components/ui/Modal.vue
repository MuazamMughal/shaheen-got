<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    maxWidth: { type: String, default: '2xl' }, // sm | md | lg | xl | 2xl | 4xl | 6xl
    closeable: { type: Boolean, default: true },
    title: { type: String, default: null },
});

const emit = defineEmits(['close']);
const dialog = ref();
const showSlot = ref(props.show);

watch(
    () => props.show,
    (open) => {
        if (open) {
            document.body.style.overflow = 'hidden';
            showSlot.value = true;
            dialog.value?.showModal();
        } else {
            document.body.style.overflow = '';
            setTimeout(() => {
                dialog.value?.close();
                showSlot.value = false;
            }, 200);
        }
    },
);

const close = () => {
    if (props.closeable) emit('close');
};

const onKeydown = (e) => {
    if (e.key === 'Escape' && props.show) {
        e.preventDefault();
        close();
    }
};

onMounted(() => {
    document.addEventListener('keydown', onKeydown);
    if (props.show) dialog.value?.showModal();
});
onUnmounted(() => {
    document.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
});

const maxWidthClass = computed(() => ({
    sm: 'sm:max-w-sm', md: 'sm:max-w-md', lg: 'sm:max-w-lg', xl: 'sm:max-w-xl',
    '2xl': 'sm:max-w-2xl', '4xl': 'sm:max-w-4xl', '6xl': 'sm:max-w-6xl',
}[props.maxWidth] ?? 'sm:max-w-2xl'));
</script>

<template>
    <dialog ref="dialog" class="z-50 m-0 min-h-full min-w-full overflow-y-auto bg-transparent backdrop:bg-transparent">
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0" scroll-region>
            <Transition
                enter-active-class="ease-out duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100"
                leave-active-class="ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0"
            >
                <div v-show="show" class="fixed inset-0 bg-secondary-900/50 backdrop-blur-sm transition-opacity" @click="close" />
            </Transition>

            <Transition
                enter-active-class="ease-out duration-200"
                enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                enter-to-class="opacity-100 translate-y-0 sm:scale-100"
                leave-active-class="ease-in duration-150"
                leave-from-class="opacity-100 translate-y-0 sm:scale-100"
                leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            >
                <div
                    v-show="show"
                    :class="['relative mb-6 overflow-hidden rounded-2xl bg-white shadow-2xl transition-all sm:mx-auto sm:w-full', maxWidthClass]"
                    role="dialog" aria-modal="true"
                >
                    <div v-if="title || closeable" class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                        <h3 class="text-lg font-semibold">{{ title }}</h3>
                        <button
                            v-if="closeable" type="button" @click="close"
                            class="grid size-9 place-items-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                            aria-label="Close"
                        >
                            <svg class="size-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                            </svg>
                        </button>
                    </div>
                    <div v-if="showSlot" class="px-6 py-5"><slot /></div>
                    <div v-if="$slots.footer && showSlot" class="flex flex-col-reverse gap-2 border-t border-slate-100 bg-slate-50/60 px-6 py-4 sm:flex-row sm:justify-end">
                        <slot name="footer" />
                    </div>
                </div>
            </Transition>
        </div>
    </dialog>
</template>
