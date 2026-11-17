<script setup>
import { reactive, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { ChevronDownIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Select from '@/Components/ui/Select.vue';
import Input from '@/Components/ui/Input.vue';
import Button from '@/Components/ui/Button.vue';
import Badge from '@/Components/ui/Badge.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';

const props = defineProps({
    activities: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    areas: { type: Array, required: true },
    users: { type: Array, required: true },
});

const filters = reactive({
    area: props.filters.area ?? '', event: props.filters.event ?? '', user: props.filters.user ?? '',
    from: props.filters.from ?? '', to: props.filters.to ?? '',
});
let timer;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => router.get(route('admin.activity.index'), Object.fromEntries(Object.entries(filters).filter(([, v]) => v)), {
        preserveState: true, preserveScroll: true, replace: true,
    }), 250);
});
const clear = () => Object.keys(filters).forEach((k) => (filters[k] = ''));
const active = () => Object.values(filters).some(Boolean);

const open = ref(new Set());
const toggle = (id) => (open.value.has(id) ? open.value.delete(id) : open.value.add(id), (open.value = new Set(open.value)));

const eventColor = { created: 'green', updated: 'blue', deleted: 'red' };
</script>

<template>
    <AdminLayout title="Activity Log" subtitle="Who changed what, and when">
        <div class="card mb-5 grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-5">
            <Select v-model="filters.area" label="Area" :options="[{ value: '', label: 'All areas' }, ...areas]" />
            <Select v-model="filters.event" label="Action" :options="[{ value: '', label: 'All' }, { value: 'created', label: 'Created' }, { value: 'updated', label: 'Updated' }, { value: 'deleted', label: 'Deleted' }]" />
            <Select v-model="filters.user" label="By" :options="[{ value: '', label: 'Anyone' }, ...users]" />
            <Input v-model="filters.from" type="date" label="From" />
            <div class="flex items-end gap-2">
                <Input v-model="filters.to" type="date" label="To" class="flex-1" />
                <Button v-if="active()" variant="ghost" aria-label="Clear filters" @click="clear"><XMarkIcon class="size-5" /></Button>
            </div>
        </div>

        <div class="card overflow-hidden">
            <ul v-if="activities.data.length" class="divide-y divide-slate-100">
                <li v-for="a in activities.data" :key="a.id">
                    <button type="button" class="flex w-full flex-wrap items-center gap-x-4 gap-y-1 px-5 py-3.5 text-left transition hover:bg-slate-50" :aria-expanded="open.has(a.id)" :disabled="!a.changes.length" @click="toggle(a.id)">
                        <Badge :color="eventColor[a.event] ?? 'slate'">{{ a.event }}</Badge>
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ a.area }}</span>
                        <span class="min-w-0 flex-1 truncate text-sm">
                            <component :is="a.subject_url ? Link : 'span'" :href="a.subject_url" class="font-semibold text-slate-900" :class="a.subject_url && 'hover:text-secondary'" @click.stop>{{ a.subject ?? '—' }}</component>
                            <span v-if="a.description !== a.event" class="text-slate-500"> · {{ a.description }}</span>
                        </span>
                        <span class="text-sm text-slate-600">{{ a.by }}</span>
                        <span class="w-36 text-right text-xs text-slate-400" :title="a.at">{{ a.ago }}</span>
                        <ChevronDownIcon v-if="a.changes.length" :class="['size-4 text-slate-400 transition', open.has(a.id) && 'rotate-180']" />
                        <span v-else class="size-4" />
                    </button>
                    <div v-if="open.has(a.id)" class="overflow-x-auto bg-slate-50/70 px-5 pb-4">
                        <table class="min-w-full text-sm">
                            <thead class="text-left text-xs uppercase tracking-wider text-slate-500">
                                <tr><th class="py-2 pr-4">Field</th><th class="py-2 pr-4">Before</th><th class="py-2">After</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="c in a.changes" :key="c.field">
                                    <td class="py-2 pr-4 font-medium text-slate-700">{{ c.field }}</td>
                                    <td class="py-2 pr-4 text-rose-700"><span :class="c.old !== null && 'line-through decoration-rose-300'">{{ c.old ?? '—' }}</span></td>
                                    <td class="py-2 text-emerald-700">{{ c.new ?? '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </li>
            </ul>
            <EmptyState v-else title="No activity found" description="Changes to flights, bookings, agents and settings are recorded here." />

            <div v-if="activities.last_page > 1" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-5 py-3 text-xs text-slate-500">
                <span>{{ activities.from }}–{{ activities.to }} of {{ activities.total }}</span>
                <div class="flex gap-1">
                    <Link v-if="activities.prev_page_url" :href="activities.prev_page_url" preserve-scroll class="rounded-lg px-3 py-1.5 font-semibold text-slate-600 hover:bg-slate-100">Previous</Link>
                    <Link v-if="activities.next_page_url" :href="activities.next_page_url" preserve-scroll class="rounded-lg px-3 py-1.5 font-semibold text-slate-600 hover:bg-slate-100">Next</Link>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
