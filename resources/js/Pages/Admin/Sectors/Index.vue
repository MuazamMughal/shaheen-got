<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { PlusIcon, PencilSquareIcon, TrashIcon, ArrowsRightLeftIcon, ArrowLongRightIcon } from '@heroicons/vue/24/outline';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import Toggle from '@/Components/ui/Toggle.vue';
import Modal from '@/Components/ui/Modal.vue';
import Badge from '@/Components/ui/Badge.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import ReorderButtons from '@/Components/ui/ReorderButtons.vue';
import { useMasterList } from '@/Composables/useMasterList';
import { focusFirstError, rules as r, useValidation } from '@/Composables/useValidation';

const props = defineProps({
    sectors: { type: Array, required: true },
    categories: { type: Array, required: true },
    tripTypes: { type: Array, required: true },
    filters: { type: Object, default: () => ({}) },
});

const { rows, editing, deleting, busy, openCreate, openEdit, closeForm, toggle, destroy, move } =
    useMasterList({ routePrefix: 'admin.sectors', items: () => props.sectors });

const categoryFilter = ref(props.filters.category ?? '');
watch(categoryFilter, (v) =>
    router.get(route('admin.sectors.index'), v ? { category: v } : {}, { preserveState: true, preserveScroll: true, replace: true }),
);

// Group rows by category for display; reordering happens within a group.
const groups = computed(() => {
    const map = new Map();
    for (const s of rows.value) {
        if (!map.has(s.category_id)) map.set(s.category_id, { id: s.category_id, name: s.category, items: [] });
        map.get(s.category_id).items.push(s);
    }
    return [...map.values()];
});

const form = useForm({ category_id: '', origin: '', destination: '', trip_type: 'return', name: '', is_active: true });
const nameTouched = ref(false);

// Mirrors SectorRequest.
const { validate, reset: resetValidation } = useValidation(form, {
    category_id: [r.required('Choose a category.')],
    origin: [r.required('Enter where the flight departs from.'), r.max(80)],
    destination: [r.required('Enter the destination.'), r.max(80)],
    trip_type: [r.required('Choose a trip type.')],
    name: [r.required('Enter the sector name.'), r.max(160)],
});

watch(editing, (record) => {
    if (!record) return;
    resetValidation();
    form.clearErrors();
    form.defaults({
        category_id: record.category_id ?? (categoryFilter.value || ''),
        origin: record.origin ?? '', destination: record.destination ?? '',
        trip_type: record.trip_type ?? 'return', name: record.name ?? '', is_active: record.is_active ?? true,
    });
    form.reset();
    nameTouched.value = !!record.id;
});

// Suggest LAHORE-JEDDAH-LAHORE / LAHORE-JEDDAH until the admin edits the name by hand.
const suggestedName = computed(() => {
    const o = form.origin.trim().toUpperCase();
    const d = form.destination.trim().toUpperCase();
    if (!o || !d) return '';
    return form.trip_type === 'return' ? `${o}-${d}-${o}` : `${o}-${d}`;
});
watch(suggestedName, (v) => { if (!nameTouched.value) form.name = v; });

function submit() {
    const opts = { preserveScroll: true, onSuccess: closeForm, onError: () => focusFirstError() };
    if (!validate()) return;
    if (editing.value?.id) form.put(route('admin.sectors.update', editing.value.id), opts);
    else form.post(route('admin.sectors.store'), opts);
}
</script>

<template>
    <AdminLayout title="Sectors" subtitle="Sub-categories inside each category, e.g. MULTAN-JEDDAH-MULTAN">
        <template #actions>
            <Button class="hidden sm:inline-flex" :disabled="!categories.length" @click="openCreate"><PlusIcon class="size-4" /> New sector</Button>
        </template>

        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center">
            <Select v-model="categoryFilter" :options="[{ value: '', label: 'All categories' }, ...categories]" aria-label="Filter by category" class="sm:w-72" />
            <Button class="sm:hidden" block :disabled="!categories.length" @click="openCreate"><PlusIcon class="size-4" /> New sector</Button>
        </div>

        <div v-if="!categories.length" class="card">
            <EmptyState title="Create a category first" description="Sectors belong to a category such as “Umrah Groups”.">
                <Button :href="route('admin.categories.index')">Go to categories</Button>
            </EmptyState>
        </div>

        <div v-else-if="groups.length" class="space-y-6">
            <section v-for="group in groups" :key="group.id" class="card overflow-hidden">
                <header class="flex items-center justify-between bg-secondary px-5 py-3">
                    <h2 class="font-display text-sm font-semibold !text-white">{{ group.name }}</h2>
                    <span class="text-xs text-tertiary">{{ group.items.length }} {{ group.items.length === 1 ? 'sector' : 'sectors' }}</span>
                </header>
                <ul class="divide-y divide-slate-100">
                    <li v-for="(s, i) in group.items" :key="s.id" :class="['flex flex-wrap items-center gap-4 px-5 py-3.5 transition hover:bg-tertiary-50/40', !s.is_active && 'opacity-60']">
                        <ReorderButtons :first="i === 0" :last="i === group.items.length - 1" :label="s.name" @up="move(s, -1, group.items)" @down="move(s, 1, group.items)" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-display font-semibold tracking-wide text-slate-900">{{ s.name }}</p>
                            <p class="mt-0.5 flex items-center gap-1.5 text-xs text-slate-500">
                                {{ s.origin }}
                                <ArrowsRightLeftIcon v-if="s.trip_type === 'return'" class="size-3.5 text-tertiary-600" />
                                <ArrowLongRightIcon v-else class="size-3.5 text-tertiary-600" />
                                {{ s.destination }}
                            </p>
                        </div>
                        <Badge :color="s.trip_type === 'return' ? 'navy' : 'gold'">{{ s.trip_type_label }}</Badge>
                        <Toggle :model-value="s.is_active" :sr-label="`Show ${s.name} to agents`" @update:model-value="toggle(s)" />
                        <div class="flex gap-0.5">
                            <button type="button" class="grid size-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-secondary" :aria-label="`Edit ${s.name}`" @click="openEdit(s)"><PencilSquareIcon class="size-5" /></button>
                            <button type="button" class="grid size-8 place-items-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600" :aria-label="`Delete ${s.name}`" @click="deleting = s"><TrashIcon class="size-5" /></button>
                        </div>
                    </li>
                </ul>
            </section>
        </div>

        <div v-else class="card">
            <EmptyState title="No sectors yet" description="Add routes like LAHORE-JEDDAH-LAHORE under a category.">
                <Button @click="openCreate"><PlusIcon class="size-4" /> Create first sector</Button>
            </EmptyState>
        </div>

        <Modal :show="!!editing" max-width="xl" :title="editing?.id ? 'Edit sector' : 'New sector'" @close="closeForm">
            <form id="sector-form" class="grid gap-5 sm:grid-cols-2" novalidate @submit.prevent="submit">
                <Select v-model="form.category_id" label="Category" :options="categories" placeholder="Select category" :error="form.errors.category_id" required class="sm:col-span-2" />
                <Input v-model="form.origin" label="From" placeholder="MULTAN" :error="form.errors.origin" required class="[&_input]:uppercase" />
                <Input v-model="form.destination" label="To" placeholder="JEDDAH" :error="form.errors.destination" required class="[&_input]:uppercase" />
                <div class="sm:col-span-2">
                    <p class="mb-1.5 text-sm font-medium text-slate-700">Trip type</p>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            v-for="t in tripTypes" :key="t.value" type="button" @click="form.trip_type = t.value"
                            :class="['h-11 rounded-xl border text-sm font-semibold transition', form.trip_type === t.value ? 'border-secondary bg-secondary text-white' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300']"
                            :aria-pressed="form.trip_type === t.value"
                        >{{ t.label }}</button>
                    </div>
                </div>
                <Input
                    v-model="form.name" label="Sector name" :error="form.errors.name" required class="sm:col-span-2 [&_input]:uppercase"
                    hint="Filled in automatically — edit if needed." @input="nameTouched = true"
                />
                <Toggle v-model="form.is_active" label="Visible to agents" class="sm:col-span-2" />
            </form>
            <template #footer>
                <Button variant="secondary" @click="closeForm">Cancel</Button>
                <Button type="submit" form="sector-form" :loading="form.processing">{{ editing?.id ? 'Save changes' : 'Create sector' }}</Button>
            </template>
        </Modal>

        <ConfirmDialog
            :show="!!deleting" :loading="busy" title="Delete sector?" confirm-text="Delete"
            :message="deleting ? `“${deleting.name}” will be removed.` : ''"
            @close="deleting = null" @confirm="destroy"
        />
    </AdminLayout>
</template>
