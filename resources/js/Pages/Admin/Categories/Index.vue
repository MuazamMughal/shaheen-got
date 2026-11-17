<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { PlusIcon, PencilSquareIcon, TrashIcon, ChevronLeftIcon, ChevronRightIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CategoryCard from '@/Components/CategoryCard.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import Toggle from '@/Components/ui/Toggle.vue';
import Modal from '@/Components/ui/Modal.vue';
import ImageUpload from '@/Components/ui/ImageUpload.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import Badge from '@/Components/ui/Badge.vue';
import { useMasterList } from '@/Composables/useMasterList';

const props = defineProps({ categories: { type: Array, required: true } });

const { rows, editing, deleting, busy, openCreate, openEdit, closeForm, toggle, destroy, move } =
    useMasterList({ routePrefix: 'admin.categories', items: () => props.categories });

const search = ref('');
const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    return q ? rows.value.filter((c) => `${c.name} ${c.country ?? ''}`.toLowerCase().includes(q)) : rows.value;
});

const form = useForm({
    name: '', country: '', description: '', is_active: true,
    image: null, flag_icon: null, remove_image: false, remove_flag_icon: false,
});

watch(editing, (record) => {
    if (!record) return;
    form.clearErrors();
    form.defaults({
        name: record.name ?? '', country: record.country ?? '', description: record.description ?? '',
        is_active: record.is_active ?? true, image: null, flag_icon: null, remove_image: false, remove_flag_icon: false,
    });
    form.reset();
});

function submit() {
    const opts = { preserveScroll: true, forceFormData: true, onSuccess: closeForm };
    if (editing.value?.id) {
        form.transform((d) => ({ ...d, _method: 'put' })).post(route('admin.categories.update', editing.value.id), opts);
    } else {
        form.transform((d) => d).post(route('admin.categories.store'), opts);
    }
}
</script>

<template>
    <AdminLayout title="Categories" subtitle="Main groups shown to agents, e.g. Umrah Groups, UAE Groups">
        <template #actions>
            <Button class="hidden sm:inline-flex" @click="openCreate"><PlusIcon class="size-4" /> New category</Button>
        </template>

        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <Input v-model="search" placeholder="Search categories" aria-label="Search categories" class="sm:w-80">
                <template #icon><MagnifyingGlassIcon class="size-5" /></template>
            </Input>
            <p class="text-sm text-slate-500">Use the arrows to set the order agents see.</p>
            <Button class="sm:hidden" block @click="openCreate"><PlusIcon class="size-4" /> New category</Button>
        </div>

        <div v-if="filtered.length" class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
            <CategoryCard v-for="(c, i) in filtered" :key="c.id" :category="c" :muted="!c.is_active">
                <template #badge>
                    <span class="absolute left-3 top-3"><Badge :color="c.is_active ? 'green' : 'slate'" dot>{{ c.is_active ? 'Active' : 'Hidden' }}</Badge></span>
                </template>
                <p class="mt-3 text-xs text-slate-500">{{ c.sectors_count }} {{ c.sectors_count === 1 ? 'sector' : 'sectors' }}</p>
                <div class="mt-4 flex items-center justify-between border-t border-dashed border-slate-200 pt-4">
                    <Toggle :model-value="c.is_active" :sr-label="`Show ${c.name} to agents`" @update:model-value="toggle(c)" />
                    <div class="flex items-center gap-0.5">
                        <template v-if="!search">
                            <button type="button" :disabled="i === 0" class="grid size-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-secondary disabled:opacity-25" :aria-label="`Move ${c.name} earlier`" @click="move(c, -1)"><ChevronLeftIcon class="size-4" /></button>
                            <button type="button" :disabled="i === filtered.length - 1" class="grid size-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-secondary disabled:opacity-25" :aria-label="`Move ${c.name} later`" @click="move(c, 1)"><ChevronRightIcon class="size-4" /></button>
                        </template>
                        <button type="button" class="grid size-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-secondary" :aria-label="`Edit ${c.name}`" @click="openEdit(c)"><PencilSquareIcon class="size-5" /></button>
                        <button type="button" class="grid size-8 place-items-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600" :aria-label="`Delete ${c.name}`" @click="deleting = c"><TrashIcon class="size-5" /></button>
                    </div>
                </div>
            </CategoryCard>
        </div>

        <div v-else class="card">
            <EmptyState :title="search ? 'No matching categories' : 'No categories yet'" description="Categories are the main groups agents browse first, like “Umrah Groups”.">
                <Button v-if="!search" @click="openCreate"><PlusIcon class="size-4" /> Create first category</Button>
            </EmptyState>
        </div>

        <Modal :show="!!editing" max-width="2xl" :title="editing?.id ? 'Edit category' : 'New category'" @close="closeForm">
            <form id="category-form" class="grid gap-5 sm:grid-cols-2" @submit.prevent="submit">
                <Input v-model="form.name" label="Name" placeholder="Umrah Groups" :error="form.errors.name" required class="sm:col-span-2" />
                <Input v-model="form.country" label="Country" placeholder="Kingdom of Saudi Arabia" :error="form.errors.country" class="sm:col-span-2" />
                <ImageUpload
                    v-model="form.image" label="Cover image" :current="editing?.image_url" :removed="form.remove_image"
                    :error="form.errors.image" hint="Landscape photo, max 4 MB" @remove="form.remove_image = true"
                />
                <ImageUpload
                    v-model="form.flag_icon" label="Flag icon" :current="editing?.flag_url" :removed="form.remove_flag_icon"
                    :error="form.errors.flag_icon" hint="Square, max 1 MB" aspect="aspect-[16/9]" fit="object-contain"
                    accept="image/png,image/jpeg,image/webp" @remove="form.remove_flag_icon = true"
                />
                <Textarea v-model="form.description" label="Description" :error="form.errors.description" class="sm:col-span-2" hint="Optional, shown on the category page" />
                <Toggle v-model="form.is_active" label="Visible to agents" description="Hidden categories are kept but not shown on the agent portal." class="sm:col-span-2" />
            </form>
            <template #footer>
                <Button variant="secondary" @click="closeForm">Cancel</Button>
                <Button type="submit" form="category-form" :loading="form.processing">{{ editing?.id ? 'Save changes' : 'Create category' }}</Button>
            </template>
        </Modal>

        <ConfirmDialog
            :show="!!deleting" :loading="busy" title="Delete category?" confirm-text="Delete"
            :message="deleting ? `“${deleting.name}” will be removed. Categories with sectors can't be deleted.` : ''"
            @close="deleting = null" @confirm="destroy"
        />
    </AdminLayout>
</template>
