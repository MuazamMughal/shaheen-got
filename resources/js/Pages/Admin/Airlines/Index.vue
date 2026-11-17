<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { PlusIcon, PencilSquareIcon, TrashIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Toggle from '@/Components/ui/Toggle.vue';
import Modal from '@/Components/ui/Modal.vue';
import Badge from '@/Components/ui/Badge.vue';
import ImageUpload from '@/Components/ui/ImageUpload.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import { useMasterList } from '@/Composables/useMasterList';

const props = defineProps({ airlines: { type: Array, required: true } });

const { rows, editing, deleting, busy, openCreate, openEdit, closeForm, toggle, destroy } =
    useMasterList({ routePrefix: 'admin.airlines', items: () => props.airlines });

const search = ref('');
const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    return q ? rows.value.filter((a) => `${a.name} ${a.iata_code ?? ''}`.toLowerCase().includes(q)) : rows.value;
});

const form = useForm({ name: '', iata_code: '', is_active: true, logo: null, remove_logo: false });

watch(editing, (record) => {
    if (!record) return;
    form.clearErrors();
    form.defaults({ name: record.name ?? '', iata_code: record.iata_code ?? '', is_active: record.is_active ?? true, logo: null, remove_logo: false });
    form.reset();
});

function submit() {
    const opts = { preserveScroll: true, forceFormData: true, onSuccess: closeForm };
    if (editing.value?.id) form.transform((d) => ({ ...d, _method: 'put' })).post(route('admin.airlines.update', editing.value.id), opts);
    else form.transform((d) => d).post(route('admin.airlines.store'), opts);
}
</script>

<template>
    <AdminLayout title="Airlines" subtitle="Carriers you publish group seats for">
        <template #actions>
            <Button class="hidden sm:inline-flex" @click="openCreate"><PlusIcon class="size-4" /> New airline</Button>
        </template>

        <div class="mb-6 flex flex-col gap-3 sm:flex-row">
            <Input v-model="search" placeholder="Search airlines" aria-label="Search airlines" class="sm:w-80">
                <template #icon><MagnifyingGlassIcon class="size-5" /></template>
            </Input>
            <Button class="sm:hidden" block @click="openCreate"><PlusIcon class="size-4" /> New airline</Button>
        </div>

        <div v-if="filtered.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
            <div v-for="a in filtered" :key="a.id" :class="['card flex items-center gap-4 p-4', !a.is_active && 'opacity-60']">
                <div class="grid h-14 w-20 shrink-0 place-items-center overflow-hidden rounded-xl border border-slate-100 bg-white">
                    <img v-if="a.logo_url" :src="a.logo_url" :alt="a.name" class="max-h-10 max-w-16 object-contain" />
                    <span v-else class="font-display text-lg font-bold text-secondary">{{ a.iata_code ?? a.name.slice(0, 2).toUpperCase() }}</span>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate font-semibold text-slate-900">{{ a.name }}</p>
                    <div class="mt-1 flex items-center gap-2">
                        <Badge v-if="a.iata_code" color="gold">{{ a.iata_code }}</Badge>
                        <Toggle :model-value="a.is_active" :sr-label="`Activate ${a.name}`" @update:model-value="toggle(a)" />
                    </div>
                </div>
                <div class="flex flex-col gap-0.5">
                    <button type="button" class="grid size-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-secondary" :aria-label="`Edit ${a.name}`" @click="openEdit(a)"><PencilSquareIcon class="size-5" /></button>
                    <button type="button" class="grid size-8 place-items-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600" :aria-label="`Delete ${a.name}`" @click="deleting = a"><TrashIcon class="size-5" /></button>
                </div>
            </div>
        </div>

        <div v-else class="card">
            <EmptyState :title="search ? 'No matching airlines' : 'No airlines yet'" description="Add carriers like Saudia, FlyJinnah or PIA with their logos.">
                <Button v-if="!search" @click="openCreate"><PlusIcon class="size-4" /> Add first airline</Button>
            </EmptyState>
        </div>

        <Modal :show="!!editing" max-width="lg" :title="editing?.id ? 'Edit airline' : 'New airline'" @close="closeForm">
            <form id="airline-form" class="grid gap-5" @submit.prevent="submit">
                <div class="grid gap-5 sm:grid-cols-[1fr_120px]">
                    <Input v-model="form.name" label="Airline name" placeholder="Saudia" :error="form.errors.name" required />
                    <Input v-model="form.iata_code" label="IATA code" placeholder="SV" maxlength="3" :error="form.errors.iata_code" class="[&_input]:uppercase" />
                </div>
                <ImageUpload
                    v-model="form.logo" label="Logo" :current="editing?.logo_url" :removed="form.remove_logo" :error="form.errors.logo"
                    aspect="aspect-[3/1]" fit="object-contain" accept="image/png,image/jpeg,image/webp"
                    hint="Transparent PNG works best, max 2 MB" @remove="form.remove_logo = true"
                />
                <Toggle v-model="form.is_active" label="Active" description="Inactive airlines can't be picked for new flights." />
            </form>
            <template #footer>
                <Button variant="secondary" @click="closeForm">Cancel</Button>
                <Button type="submit" form="airline-form" :loading="form.processing">{{ editing?.id ? 'Save changes' : 'Create airline' }}</Button>
            </template>
        </Modal>

        <ConfirmDialog
            :show="!!deleting" :loading="busy" title="Delete airline?" confirm-text="Delete"
            :message="deleting ? `“${deleting.name}” will be removed.` : ''"
            @close="deleting = null" @confirm="destroy"
        />
    </AdminLayout>
</template>
