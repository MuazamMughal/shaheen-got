<script setup>
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { PlusIcon, PencilSquareIcon, TrashIcon, BriefcaseIcon } from '@heroicons/vue/24/outline';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Toggle from '@/Components/ui/Toggle.vue';
import Modal from '@/Components/ui/Modal.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import ReorderButtons from '@/Components/ui/ReorderButtons.vue';
import { useMasterList } from '@/Composables/useMasterList';
import { focusFirstError, rules as r, useValidation } from '@/Composables/useValidation';

const props = defineProps({ baggageTypes: { type: Array, required: true } });

const { rows, editing, deleting, busy, openCreate, openEdit, closeForm, toggle, destroy, move } =
    useMasterList({ routePrefix: 'admin.baggage_types', items: () => props.baggageTypes });

const form = useForm({ label: '', description: '', is_active: true });

// Mirrors BaggageTypeRequest.
const { validate, reset: resetValidation } = useValidation(form, {
    label: [r.required('Enter a label, e.g. 20+10 KG.'), r.max(120)],
    description: [r.max(255)],
});

watch(editing, (record) => {
    if (!record) return;
    resetValidation();
    form.clearErrors();
    form.defaults({ label: record.label ?? '', description: record.description ?? '', is_active: record.is_active ?? true });
    form.reset();
});

function submit() {
    const opts = { preserveScroll: true, onSuccess: closeForm, onError: () => focusFirstError() };
    if (!validate()) return;
    if (editing.value?.id) form.put(route('admin.baggage_types.update', editing.value.id), opts);
    else form.post(route('admin.baggage_types.store'), opts);
}
</script>

<template>
    <AdminLayout title="Baggage Types" subtitle="Allowances you can attach to flights">
        <template #actions>
            <Button class="hidden sm:inline-flex" @click="openCreate"><PlusIcon class="size-4" /> New baggage type</Button>
        </template>
        <Button class="mb-4 sm:hidden" block @click="openCreate"><PlusIcon class="size-4" /> New baggage type</Button>

        <div v-if="rows.length" class="card mx-auto max-w-3xl overflow-hidden">
            <ul class="divide-y divide-slate-100">
                <li v-for="(b, i) in rows" :key="b.id" :class="['flex items-center gap-4 px-5 py-3.5 transition hover:bg-tertiary-50/40', !b.is_active && 'opacity-60']">
                    <ReorderButtons :first="i === 0" :last="i === rows.length - 1" :label="b.label" @up="move(b, -1)" @down="move(b, 1)" />
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-tertiary-50 text-tertiary-700"><BriefcaseIcon class="size-5" /></span>
                    <div class="min-w-0 flex-1">
                        <p class="font-display font-semibold text-slate-900">{{ b.label }}</p>
                        <p v-if="b.description" class="truncate text-xs text-slate-500">{{ b.description }}</p>
                    </div>
                    <Toggle :model-value="b.is_active" :sr-label="`Activate ${b.label}`" @update:model-value="toggle(b)" />
                    <div class="flex gap-0.5">
                        <button type="button" class="grid size-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-secondary" :aria-label="`Edit ${b.label}`" @click="openEdit(b)"><PencilSquareIcon class="size-5" /></button>
                        <button type="button" class="grid size-8 place-items-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600" :aria-label="`Delete ${b.label}`" @click="deleting = b"><TrashIcon class="size-5" /></button>
                    </div>
                </li>
            </ul>
        </div>

        <div v-else class="card">
            <EmptyState title="No baggage types yet" description="Add allowances like “20+10 KG” or “1PC X 23 KG”.">
                <Button @click="openCreate"><PlusIcon class="size-4" /> Add first baggage type</Button>
            </EmptyState>
        </div>

        <Modal :show="!!editing" max-width="lg" :title="editing?.id ? 'Edit baggage type' : 'New baggage type'" @close="closeForm">
            <form id="baggage-form" class="grid gap-5" novalidate @submit.prevent="submit">
                <Input v-model="form.label" label="Label" placeholder="20+10 KG" :error="form.errors.label" required class="[&_input]:uppercase" />
                <Input v-model="form.description" label="Description" placeholder="20 kg checked + 10 kg hand carry" :error="form.errors.description" />
                <Toggle v-model="form.is_active" label="Active" />
            </form>
            <template #footer>
                <Button variant="secondary" @click="closeForm">Cancel</Button>
                <Button type="submit" form="baggage-form" :loading="form.processing">{{ editing?.id ? 'Save changes' : 'Create' }}</Button>
            </template>
        </Modal>

        <ConfirmDialog
            :show="!!deleting" :loading="busy" title="Delete baggage type?" confirm-text="Delete"
            :message="deleting ? `“${deleting.label}” will be removed.` : ''"
            @close="deleting = null" @confirm="destroy"
        />
    </AdminLayout>
</template>
