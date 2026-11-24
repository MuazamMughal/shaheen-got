<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import { focusFirstError, rules as r, useValidation } from '@/Composables/useValidation';

const props = defineProps({
    staff: { type: Object, default: null },
    roles: { type: Array, required: true },
});

const editing = computed(() => !!props.staff);

const form = useForm({
    name: props.staff?.name ?? '',
    email: props.staff?.email ?? '',
    phone: props.staff?.phone ?? '',
    role_id: props.staff?.role_id ?? '',
    status: props.staff?.status ?? 'approved',
    password: '',
    password_confirmation: '',
});

// Mirrors StaffRequest. On edit the password is optional (blank keeps the current one).
const { validate, reset: resetValidation } = useValidation(form, {
    name: [r.required('Enter the full name.'), r.max(255)],
    email: [r.required('Enter an email address.'), r.email(), r.max(255)],
    phone: [r.max(30), r.pattern(/^[0-9+\-\s()]*$/, 'Phone can only contain digits, spaces and + - ( ).')],
    role_id: [r.required('Choose a role.')],
    status: [r.required('Choose a status.')],
    password: [...(editing.value ? [] : [r.required('Choose a password.')]), r.min(8, 'Password must be at least 8 characters.')],
    password_confirmation: [
        (v) => (form.password && !v ? 'Confirm the password.' : null),
        r.same(() => form.password, 'Passwords do not match.'),
    ],
});

function submit() {
    if (!validate()) return;
    const opts = { onError: () => focusFirstError(), onFinish: () => { resetValidation(); form.reset('password', 'password_confirmation'); } };
    if (editing.value) form.put(route('admin.staff.update', props.staff.id), opts);
    else form.post(route('admin.staff.store'), opts);
}
</script>

<template>
    <AdminLayout :title="editing ? `Edit ${staff.name}` : 'Add staff member'" subtitle="Staff sign in to the admin panel with the role you assign">
        <form class="mx-auto max-w-3xl space-y-6" novalidate @submit.prevent="submit">
            <section class="card p-6">
                <h2 class="text-base font-semibold">Details</h2>
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <Input v-model="form.name" label="Full name" :error="form.errors.name" required autofocus class="sm:col-span-2" />
                    <Input v-model="form.email" label="Email address" type="email" :error="form.errors.email" required autocomplete="off" />
                    <Input v-model="form.phone" label="Phone" :error="form.errors.phone" placeholder="03xx xxxxxxx" />
                </div>
            </section>

            <section class="card p-6">
                <h2 class="text-base font-semibold">Access</h2>
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div>
                        <Select v-model="form.role_id" label="Role" :options="roles" placeholder="Select a role" :error="form.errors.role_id" required />
                        <p v-if="!roles.length" class="mt-1.5 text-xs text-amber-700">
                            No roles yet — <Link :href="route('admin.roles.create')" class="font-semibold underline">create one first</Link>.
                        </p>
                    </div>
                    <Select
                        v-model="form.status" label="Status" :error="form.errors.status" required
                        :options="[{ value: 'approved', label: 'Active — can sign in' }, { value: 'suspended', label: 'Inactive — cannot sign in' }]"
                    />
                </div>
            </section>

            <section class="card p-6">
                <h2 class="text-base font-semibold">{{ editing ? 'Change password' : 'Password' }}</h2>
                <p v-if="editing" class="mt-1 text-sm text-slate-500">Leave blank to keep the current password.</p>
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <Input v-model="form.password" label="Password" type="password" :error="form.errors.password" :required="!editing" autocomplete="new-password" />
                    <Input v-model="form.password_confirmation" label="Confirm password" type="password" :error="form.errors.password_confirmation" :required="!editing" autocomplete="new-password" />
                </div>
            </section>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
                <Button variant="ghost" :href="route('admin.staff.index')"><ArrowLeftIcon class="size-4" /> Back to staff</Button>
                <Button type="submit" :loading="form.processing">{{ editing ? 'Save changes' : 'Create staff member' }}</Button>
            </div>
        </form>
    </AdminLayout>
</template>
