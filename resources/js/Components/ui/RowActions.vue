<script setup>
/**
 * "⋯" dropdown for table rows. The menu is teleported to <body> with fixed
 * positioning so it is never clipped by a scrolling table container.
 *
 * <RowActions :label="`Actions for ${row.code}`">
 *   <template #default="{ close }"> <RowActionItem ... /> </template>
 * </RowActions>
 */
import { nextTick, onBeforeUnmount, ref } from 'vue';
import { EllipsisVerticalIcon } from '@heroicons/vue/24/outline';

defineProps({ label: { type: String, default: 'Actions' } });

const open = ref(false);
const button = ref(null);
const menu = ref(null);
const pos = ref({ top: 0, left: 0 });

async function toggle() {
    if (open.value) return close();
    const r = button.value.getBoundingClientRect();
    open.value = true;
    await nextTick();
    const width = menu.value?.offsetWidth ?? 176;
    const height = menu.value?.offsetHeight ?? 0;
    const below = r.bottom + 4 + height <= window.innerHeight;
    pos.value = {
        top: below ? r.bottom + 4 : Math.max(8, r.top - height - 4),
        left: Math.max(8, Math.min(r.right - width, window.innerWidth - width - 8)),
    };
    menu.value?.querySelector('a,button')?.focus();
    document.addEventListener('mousedown', onOutside);
    document.addEventListener('keydown', onKey);
    window.addEventListener('scroll', close, true);
    window.addEventListener('resize', close);
}

function close() {
    open.value = false;
    document.removeEventListener('mousedown', onOutside);
    document.removeEventListener('keydown', onKey);
    window.removeEventListener('scroll', close, true);
    window.removeEventListener('resize', close);
}

function onOutside(e) {
    if (!menu.value?.contains(e.target) && !button.value?.contains(e.target)) close();
}
function onKey(e) {
    if (e.key === 'Escape') { close(); button.value?.focus(); }
}

onBeforeUnmount(close);
</script>

<template>
    <button
        ref="button" type="button" :aria-label="label" aria-haspopup="menu" :aria-expanded="open"
        class="grid size-8 place-items-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-secondary"
        @click="toggle"
    >
        <EllipsisVerticalIcon class="size-5" />
    </button>
    <Teleport to="body">
        <div
            v-if="open" ref="menu" role="menu" :style="{ top: pos.top + 'px', left: pos.left + 'px' }"
            class="fixed z-[70] w-44 rounded-xl border border-slate-100 bg-white p-1 text-left shadow-xl"
            @click="close"
        >
            <slot :close="close" />
        </div>
    </Teleport>
</template>
