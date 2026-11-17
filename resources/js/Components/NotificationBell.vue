<script setup>
/**
 * Bell with unread count. Polls every 30s (no websockets on shared hosting),
 * loads the list when opened, marks items read on click.
 */
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { BellIcon, CheckIcon } from '@heroicons/vue/24/outline';
import axios from 'axios';

const page = usePage();
const unread = ref(page.props.auth?.unread_notifications ?? 0);
const items = ref([]);
const open = ref(false);
const loading = ref(false);
const root = ref(null);

async function load() {
    try {
        const { data } = await axios.get(route('notifications.index'));
        unread.value = data.unread;
        items.value = data.items;
    } catch { /* offline or signed out — try again next tick */ }
}

async function toggle() {
    open.value = !open.value;
    if (open.value) {
        loading.value = !items.value.length;
        await load();
        loading.value = false;
    }
}

async function openItem(item) {
    open.value = false;
    if (!item.read) {
        item.read = true;
        unread.value = Math.max(0, unread.value - 1);
        axios.post(route('notifications.read', item.id)).catch(() => {});
    }
    if (item.url) router.visit(item.url);
}

async function readAll() {
    items.value.forEach((i) => (i.read = true));
    unread.value = 0;
    await axios.post(route('notifications.read-all')).catch(() => {});
}

function onOutside(e) {
    if (open.value && root.value && !root.value.contains(e.target)) open.value = false;
}
function onKey(e) {
    if (e.key === 'Escape') open.value = false;
}

let timer;
onMounted(() => {
    timer = setInterval(() => { if (!document.hidden) load(); }, 30000);
    document.addEventListener('mousedown', onOutside);
    document.addEventListener('keydown', onKey);
});
onBeforeUnmount(() => {
    clearInterval(timer);
    document.removeEventListener('mousedown', onOutside);
    document.removeEventListener('keydown', onKey);
});

// Inertia navigations refresh the shared count too.
const offNavigate = router.on('success', (e) => { unread.value = e.detail.page.props.auth?.unread_notifications ?? unread.value; });
onBeforeUnmount(offNavigate);

const dots = { navy: 'bg-secondary', gold: 'bg-tertiary', green: 'bg-emerald-500', red: 'bg-rose-500', amber: 'bg-amber-500', blue: 'bg-sky-500' };
const badge = computed(() => (unread.value > 9 ? '9+' : unread.value));
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button" :aria-label="unread ? `${unread} unread notifications` : 'Notifications'" :aria-expanded="open"
            class="relative grid size-11 place-items-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:border-slate-300 hover:text-secondary focus:outline-none focus-visible:ring-4 focus-visible:ring-secondary-100"
            @click="toggle"
        >
            <BellIcon class="size-5" />
            <span v-if="unread" class="absolute -right-1 -top-1 grid h-5 min-w-5 place-items-center rounded-full bg-tertiary px-1 text-[10px] font-bold text-secondary-900 ring-2 ring-white">{{ badge }}</span>
        </button>

        <Transition enter-active-class="transition duration-100 ease-out" enter-from-class="scale-95 opacity-0" leave-active-class="transition duration-75 ease-in" leave-to-class="scale-95 opacity-0">
            <div v-if="open" class="fixed inset-x-3 top-20 z-50 origin-top-right overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-2xl sm:absolute sm:inset-x-auto sm:right-0 sm:top-auto sm:mt-2 sm:w-96">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                    <p class="font-semibold text-slate-900">Notifications</p>
                    <button v-if="unread" type="button" class="inline-flex items-center gap-1 text-xs font-semibold text-secondary hover:text-tertiary-600" @click="readAll">
                        <CheckIcon class="size-4" /> Mark all read
                    </button>
                </div>
                <div class="max-h-[60vh] overflow-y-auto">
                    <p v-if="loading" class="px-4 py-8 text-center text-sm text-slate-400">Loading…</p>
                    <p v-else-if="!items.length" class="px-4 py-10 text-center text-sm text-slate-400">You're all caught up.</p>
                    <button
                        v-for="item in items" v-else :key="item.id" type="button" @click="openItem(item)"
                        :class="['flex w-full gap-3 border-b border-slate-50 px-4 py-3 text-left transition hover:bg-slate-50', !item.read && 'bg-tertiary-50/50']"
                    >
                        <span :class="['mt-1.5 size-2 shrink-0 rounded-full', item.read ? 'bg-slate-200' : (dots[item.tone] ?? dots.navy)]" />
                        <span class="min-w-0 flex-1">
                            <span :class="['block text-sm', item.read ? 'text-slate-600' : 'font-semibold text-slate-900']">{{ item.title }}</span>
                            <span class="mt-0.5 line-clamp-2 block text-xs text-slate-500">{{ item.body }}</span>
                        </span>
                        <span class="shrink-0 text-[11px] text-slate-400">{{ item.ago }}</span>
                    </button>
                </div>
            </div>
        </Transition>
    </div>
</template>
