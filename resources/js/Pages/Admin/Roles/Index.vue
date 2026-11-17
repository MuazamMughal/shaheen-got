<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { PlusIcon, ShieldCheckIcon, PencilSquareIcon, TrashIcon, UsersIcon } from '@heroicons/vue/24/outline';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from '@/Components/ui/Button.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';

defineProps({
    roles: { type: Array, required: true },
    totalPermissions: { type: Number, required: true },
});

const deleting = ref(null);
const busy = ref(false);

function destroy() {
    busy.value = true;
    router.delete(route('admin.roles.destroy', deleting.value.id), {
        preserveScroll: true,
        onFinish: () => { busy.value = false; deleting.value = null; },
    });
}
</script>

<template>
    <AdminLayout title="Roles & Permissions" subtitle="Control what each staff role can see and do">
        <template #actions>
            <Button :href="route('admin.roles.create')" class="hidden sm:inline-flex"><PlusIcon class="size-4" /> New role</Button>
        </template>

        <div class="mb-6 flex items-start gap-3 rounded-2xl border border-tertiary-200 bg-tertiary-50 p-4 text-sm text-slate-700">
            <ShieldCheckIcon class="mt-0.5 size-5 shrink-0 text-tertiary-700" />
            <p>
                The <strong>Super Admin</strong> always has full access. Staff only get the permissions of their role.
                Untick <strong>“View fares, prices and totals”</strong> to hide all prices from a role.
            </p>
        </div>

        <Button :href="route('admin.roles.create')" block class="mb-4 sm:hidden"><PlusIcon class="size-4" /> New role</Button>

        <div v-if="roles.length" class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            <div v-for="role in roles" :key="role.id" class="card flex flex-col p-6">
                <div class="flex items-start justify-between gap-3">
                    <div class="grid size-11 place-items-center rounded-xl bg-secondary text-tertiary">
                        <ShieldCheckIcon class="size-6" />
                    </div>
                    <div class="flex gap-1">
                        <Link :href="route('admin.roles.edit', role.id)" class="grid size-9 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-secondary" :aria-label="`Edit ${role.name}`">
                            <PencilSquareIcon class="size-5" />
                        </Link>
                        <button type="button" class="grid size-9 place-items-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600" :aria-label="`Delete ${role.name}`" @click="deleting = role">
                            <TrashIcon class="size-5" />
                        </button>
                    </div>
                </div>
                <h3 class="mt-4 font-display text-lg font-semibold">{{ role.name }}</h3>
                <div class="mt-4">
                    <div class="flex justify-between text-xs text-slate-500">
                        <span>{{ role.permissions_count }} of {{ totalPermissions }} permissions</span>
                        <span>{{ Math.round((role.permissions_count / totalPermissions) * 100) }}%</span>
                    </div>
                    <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-gradient-to-r from-secondary to-tertiary" :style="{ width: `${(role.permissions_count / totalPermissions) * 100}%` }" />
                    </div>
                </div>
                <p class="mt-4 flex items-center gap-1.5 text-sm text-slate-500">
                    <UsersIcon class="size-4" /> {{ role.users_count }} staff {{ role.users_count === 1 ? 'member' : 'members' }}
                </p>
            </div>
        </div>

        <div v-else class="card">
            <EmptyState title="No roles yet" description="Create a role such as “Booking Desk” or “Accounts”, then assign it to staff members.">
                <Button :href="route('admin.roles.create')"><PlusIcon class="size-4" /> Create first role</Button>
            </EmptyState>
        </div>

        <ConfirmDialog
            :show="!!deleting" :loading="busy" title="Delete role?" confirm-text="Delete role"
            :message="deleting ? `“${deleting.name}” will be deleted. Roles assigned to staff cannot be deleted.` : ''"
            @close="deleting = null" @confirm="destroy"
        />
    </AdminLayout>
</template>
