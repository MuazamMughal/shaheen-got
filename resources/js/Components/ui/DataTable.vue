<script setup>
/**
 * Server-driven table. Pass `columns` and Laravel paginator data (`rows`).
 * Sorting/paging update the URL via Inertia so filters survive refresh.
 *
 * columns: [{ key, label, sortable?, align?: 'left'|'right'|'center', class? }]
 * Custom cells: <template #cell-<key>="{ row, value }">…</template>
 */
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import EmptyState from './EmptyState.vue';

const props = defineProps({
    columns: { type: Array, required: true },
    rows: { type: Object, required: true }, // Laravel LengthAwarePaginator JSON (or { data: [] })
    sort: { type: String, default: null },
    direction: { type: String, default: 'asc' },
    rowKey: { type: String, default: 'id' },
    emptyTitle: { type: String, default: 'No records found' },
    emptyDescription: { type: String, default: null },
});

const data = computed(() => props.rows?.data ?? []);
const meta = computed(() => props.rows?.meta ?? props.rows ?? {});
const links = computed(() => meta.value.links ?? props.rows?.links ?? []);
const hasPages = computed(() => Array.isArray(links.value) && (meta.value.last_page ?? 1) > 1);

const alignClass = (align) => ({ right: 'text-right', center: 'text-center' }[align] ?? 'text-left');

function sortBy(col) {
    if (!col.sortable) return;
    const direction = props.sort === col.key && props.direction === 'asc' ? 'desc' : 'asc';
    const params = Object.fromEntries(new URLSearchParams(window.location.search));
    router.get(window.location.pathname, { ...params, sort: col.key, direction, page: 1 }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

const get = (row, key) => key.split('.').reduce((v, k) => v?.[k], row);
</script>

<template>
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-secondary text-white">
                    <tr>
                        <th
                            v-for="col in columns" :key="col.key" scope="col"
                            :class="['whitespace-nowrap px-4 py-3.5 text-xs font-semibold uppercase tracking-wider', alignClass(col.align), col.class]"
                            :aria-sort="sort === col.key ? (direction === 'asc' ? 'ascending' : 'descending') : undefined"
                        >
                            <button v-if="col.sortable" type="button" class="inline-flex items-center gap-1 uppercase tracking-wider hover:text-tertiary" @click="sortBy(col)">
                                {{ col.label }}
                                <svg :class="['size-3.5 transition', sort === col.key ? 'text-tertiary' : 'opacity-40', sort === col.key && direction === 'desc' ? 'rotate-180' : '']" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M10 5a.75.75 0 0 1 .53.22l4 4a.75.75 0 1 1-1.06 1.06L10 6.81 6.53 10.28a.75.75 0 0 1-1.06-1.06l4-4A.75.75 0 0 1 10 5Z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <template v-else>{{ col.label }}</template>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <tr v-for="row in data" :key="get(row, rowKey)" class="transition hover:bg-tertiary-50/50">
                        <td v-for="col in columns" :key="col.key" :class="['px-4 py-3.5 align-middle text-slate-700', alignClass(col.align), col.class]">
                            <slot :name="`cell-${col.key}`" :row="row" :value="get(row, col.key)">
                                {{ get(row, col.key) ?? '—' }}
                            </slot>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <EmptyState v-if="!data.length" :title="emptyTitle" :description="emptyDescription" />

        <div v-if="hasPages" class="flex flex-col items-center justify-between gap-3 border-t border-slate-100 px-4 py-3 sm:flex-row">
            <p class="text-xs text-slate-500">
                Showing <span class="font-semibold text-slate-700">{{ meta.from }}</span>–<span class="font-semibold text-slate-700">{{ meta.to }}</span>
                of <span class="font-semibold text-slate-700">{{ meta.total }}</span>
            </p>
            <nav class="flex flex-wrap items-center gap-1" aria-label="Pagination">
                <template v-for="(link, i) in links" :key="i">
                    <Link
                        v-if="link.url" :href="link.url" preserve-scroll preserve-state
                        :class="['grid h-8 min-w-8 place-items-center rounded-lg px-2 text-xs font-semibold transition',
                            link.active ? 'bg-secondary text-white' : 'text-slate-600 hover:bg-slate-100']"
                    ><span v-html="link.label" /></Link>
                    <span v-else class="grid h-8 min-w-8 place-items-center px-2 text-xs text-slate-300" v-html="link.label" />
                </template>
            </nav>
        </div>
    </div>
</template>
