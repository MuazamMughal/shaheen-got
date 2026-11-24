<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon, CurrencyDollarIcon } from '@heroicons/vue/24/outline';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import { focusFirstError, rules as r, useValidation } from '@/Composables/useValidation';

const props = defineProps({
    role: { type: Object, default: null },
    groups: { type: Object, required: true }, // { Module: { 'perm.name': 'Label' } }
});

const editing = computed(() => !!props.role);

const form = useForm({
    name: props.role?.name ?? '',
    permissions: [...(props.role?.permissions ?? [])],
});

const allPermissions = computed(() => Object.values(props.groups).flatMap((g) => Object.keys(g)));

const isOn = (perm) => form.permissions.includes(perm);
function toggle(perm) {
    form.permissions = isOn(perm) ? form.permissions.filter((p) => p !== perm) : [...form.permissions, perm];
}

const groupState = (group) => {
    const keys = Object.keys(group);
    const on = keys.filter(isOn).length;
    return on === 0 ? 'none' : on === keys.length ? 'all' : 'some';
};
function toggleGroup(group) {
    const keys = Object.keys(group);
    form.permissions = groupState(group) === 'all'
        ? form.permissions.filter((p) => !keys.includes(p))
        : [...new Set([...form.permissions, ...keys])];
}

const selectAll = () => (form.permissions = [...allPermissions.value]);
const clearAll = () => (form.permissions = []);

const { validate } = useValidation(form, { name: [r.required('Enter a role name.'), r.max(60)] });

function submit() {
    if (!validate()) return;
    const opts = { onError: () => focusFirstError() };
    if (editing.value) form.put(route('admin.roles.update', props.role.id), opts);
    else form.post(route('admin.roles.store'), opts);
}
</script>

<template>
    <AdminLayout :title="editing ? `Edit role: ${role.name}` : 'New role'" subtitle="Choose exactly what this role can do">
        <form class="mx-auto max-w-5xl space-y-6" novalidate @submit.prevent="submit">
            <div class="card p-6">
                <div class="grid gap-5 sm:grid-cols-[1fr_auto] sm:items-end">
                    <Input v-model="form.name" label="Role name" placeholder="e.g. Booking Desk" :error="form.errors.name" required autofocus />
                    <div class="flex gap-2">
                        <Button variant="secondary" @click="selectAll">Select all</Button>
                        <Button variant="ghost" @click="clearAll">Clear</Button>
                    </div>
                </div>
                <p v-if="role?.users_count" class="mt-3 text-xs text-slate-500">
                    Changes apply immediately to {{ role.users_count }} staff {{ role.users_count === 1 ? 'member' : 'members' }} with this role.
                </p>
                <p v-if="form.errors.permissions" class="mt-2 text-xs font-medium text-rose-600">{{ form.errors.permissions }}</p>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <fieldset
                    v-for="(perms, module) in groups" :key="module"
                    :class="['card overflow-hidden', module === 'Pricing' ? 'ring-2 ring-tertiary/50' : '']"
                >
                    <legend class="sr-only">{{ module }}</legend>
                    <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/70 px-5 py-3">
                        <div class="flex items-center gap-2">
                            <CurrencyDollarIcon v-if="module === 'Pricing'" class="size-5 text-tertiary-600" />
                            <h3 class="font-semibold">{{ module }}</h3>
                        </div>
                        <label class="flex cursor-pointer items-center gap-2 text-xs font-medium text-slate-500">
                            All
                            <input
                                type="checkbox" class="size-4 rounded border-slate-300 text-secondary focus:ring-secondary-200"
                                :checked="groupState(perms) === 'all'" :indeterminate="groupState(perms) === 'some'"
                                @change="toggleGroup(perms)"
                            />
                        </label>
                    </div>
                    <div class="divide-y divide-slate-100">
                        <label v-for="(label, perm) in perms" :key="perm" class="flex cursor-pointer items-center justify-between gap-4 px-5 py-3 transition hover:bg-tertiary-50/40">
                            <span>
                                <span class="block text-sm font-medium text-slate-800">{{ label }}</span>
                                <span class="block font-mono text-[11px] text-slate-400">{{ perm }}</span>
                            </span>
                            <!-- Toggle switch -->
                            <span class="relative inline-flex shrink-0">
                                <input type="checkbox" class="peer sr-only" :checked="isOn(perm)" @change="toggle(perm)" />
                                <span class="h-6 w-11 rounded-full bg-slate-200 transition peer-checked:bg-secondary peer-focus-visible:ring-4 peer-focus-visible:ring-secondary-100" />
                                <span class="absolute left-0.5 top-0.5 size-5 rounded-full bg-white shadow transition peer-checked:translate-x-5 peer-checked:bg-tertiary" />
                            </span>
                        </label>
                    </div>
                    <p v-if="module === 'Pricing'" class="border-t border-tertiary-100 bg-tertiary-50 px-5 py-3 text-xs text-slate-600">
                        When off, fares, seat prices and booking totals are removed on the server before reaching this staff member.
                    </p>
                </fieldset>
            </div>

            <div class="sticky bottom-4 z-10 flex flex-col-reverse gap-3 rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-lg backdrop-blur sm:flex-row sm:items-center sm:justify-between">
                <Button variant="ghost" :href="route('admin.roles.index')"><ArrowLeftIcon class="size-4" /> Back to roles</Button>
                <div class="flex items-center gap-4">
                    <span class="text-sm text-slate-500">{{ form.permissions.length }} / {{ allPermissions.length }} selected</span>
                    <Button type="submit" :loading="form.processing">{{ editing ? 'Save changes' : 'Create role' }}</Button>
                </div>
            </div>
        </form>
    </AdminLayout>
</template>
