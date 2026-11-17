<script setup>
import { reactive, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { PlusIcon, MagnifyingGlassIcon, PencilSquareIcon, NoSymbolIcon, CheckCircleIcon } from '@heroicons/vue/24/outline';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import Badge from '@/Components/ui/Badge.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';

const props = defineProps({
    staff: { type: Object, required: true },
    roles: { type: Array, required: true },
    filters: { type: Object, default: () => ({}) },
});

const filters = reactive({
    search: props.filters.search ?? '',
    role: props.filters.role ?? '',
    status: props.filters.status ?? '',
});

let timer;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        const params = Object.fromEntries(Object.entries({ ...filters, sort: props.filters.sort, direction: props.filters.direction }).filter(([, v]) => v));
        router.get(route('admin.staff.index'), params, { preserveState: true, preserveScroll: true, replace: true });
    }, 300);
});

const columns = [
    { key: 'name', label: 'Staff member', sortable: true },
    { key: 'role', label: 'Role' },
    { key: 'phone', label: 'Phone', class: 'hidden xl:table-cell' },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'last_login_at', label: 'Last login', sortable: true, class: 'hidden lg:table-cell' },
    { key: 'actions', label: '', align: 'right' },
];

const toggling = ref(null);
const busy = ref(false);
function toggleStatus() {
    busy.value = true;
    router.patch(route('admin.staff.toggle-status', toggling.value.id), {}, {
        preserveScroll: true,
        onFinish: () => { busy.value = false; toggling.value = null; },
    });
}

const initials = (name) => name.split(' ').map((p) => p[0]).slice(0, 2).join('').toUpperCase();
</script>

<template>
    <AdminLayout title="Staff" subtitle="People who work in the admin panel">
        <template #actions>
            <Button :href="route('admin.staff.create')" class="hidden sm:inline-flex"><PlusIcon class="size-4" /> Add staff</Button>
        </template>

        <div class="mb-5 grid gap-3 sm:grid-cols-[1fr_200px_180px]">
            <Input v-model="filters.search" placeholder="Search name, email or phone" aria-label="Search staff">
                <template #icon><MagnifyingGlassIcon class="size-5" /></template>
            </Input>
            <Select v-model="filters.role" :options="[{ value: '', label: 'All roles' }, ...roles]" aria-label="Filter by role" />
            <Select v-model="filters.status" :options="[{ value: '', label: 'All statuses' }, { value: 'approved', label: 'Active' }, { value: 'suspended', label: 'Inactive' }]" aria-label="Filter by status" />
        </div>

        <Button :href="route('admin.staff.create')" block class="mb-4 sm:hidden"><PlusIcon class="size-4" /> Add staff</Button>

        <DataTable
            :columns="columns" :rows="staff" :sort="props.filters.sort" :direction="props.filters.direction"
            empty-title="No staff members" empty-description="Add staff and give each one a role to control their access."
        >
            <template #cell-name="{ row }">
                <div class="flex items-center gap-3">
                    <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-secondary-50 text-xs font-semibold text-secondary">{{ initials(row.name) }}</span>
                    <div class="min-w-0">
                        <p class="truncate font-semibold text-slate-900">{{ row.name }}</p>
                        <p class="truncate text-xs text-slate-500">{{ row.email }}</p>
                    </div>
                </div>
            </template>
            <template #cell-role="{ row }">
                <Badge v-if="row.role" color="navy">{{ row.role.name }}</Badge>
                <Badge v-else color="red">No role</Badge>
            </template>
            <template #cell-status="{ row }">
                <Badge :color="row.status === 'approved' ? 'green' : 'slate'" dot>{{ row.status === 'approved' ? 'Active' : 'Inactive' }}</Badge>
            </template>
            <template #cell-last_login_at="{ value }">
                <span class="text-slate-500">{{ value ?? 'Never' }}</span>
            </template>
            <template #cell-actions="{ row }">
                <div class="flex justify-end gap-1">
                    <Link :href="route('admin.staff.edit', row.id)" class="grid size-9 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-secondary" :aria-label="`Edit ${row.name}`">
                        <PencilSquareIcon class="size-5" />
                    </Link>
                    <button
                        type="button" @click="toggling = row"
                        :class="['grid size-9 place-items-center rounded-lg text-slate-400', row.status === 'approved' ? 'hover:bg-rose-50 hover:text-rose-600' : 'hover:bg-emerald-50 hover:text-emerald-600']"
                        :aria-label="row.status === 'approved' ? `Deactivate ${row.name}` : `Activate ${row.name}`"
                    >
                        <NoSymbolIcon v-if="row.status === 'approved'" class="size-5" />
                        <CheckCircleIcon v-else class="size-5" />
                    </button>
                </div>
            </template>
        </DataTable>

        <ConfirmDialog
            :show="!!toggling" :loading="busy"
            :variant="toggling?.status === 'approved' ? 'danger' : 'success'"
            :title="toggling?.status === 'approved' ? 'Deactivate staff member?' : 'Activate staff member?'"
            :confirm-text="toggling?.status === 'approved' ? 'Deactivate' : 'Activate'"
            :message="toggling?.status === 'approved' ? `${toggling?.name} will be logged out and unable to sign in.` : `${toggling?.name} will be able to sign in again.`"
            @close="toggling = null" @confirm="toggleStatus"
        />
    </AdminLayout>
</template>
