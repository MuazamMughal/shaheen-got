<script setup>
import { useForm } from '@inertiajs/vue3';
import Input from '@/Components/ui/Input.vue';
import Button from '@/Components/ui/Button.vue';
import { focusFirstError, rules as r, useValidation } from '@/Composables/useValidation';

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

// Mirrors PasswordController@update.
const { validate, touch, reset: resetValidation } = useValidation(form, {
    current_password: [r.required('Enter your current password.')],
    password: [r.required('Choose a new password.'), r.min(8, 'Password must be at least 8 characters.')],
    password_confirmation: [r.required('Confirm your new password.'), r.same(() => form.password, 'Passwords do not match.')],
});

const updatePassword = () => {
    if (!validate()) return;
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => { resetValidation(); form.reset(); },
        onError: () => {
            resetValidation();
            if (form.errors.password) form.reset('password', 'password_confirmation');
            if (form.errors.current_password) form.reset('current_password');
            focusFirstError();
        },
    });
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-semibold">Update password</h2>
            <p class="mt-1 text-sm text-slate-500">Use a long, random password to keep your account secure.</p>
        </header>

        <form class="mt-6 max-w-xl space-y-5" novalidate @submit.prevent="updatePassword">
            <Input
                v-model="form.current_password" name="current_password" label="Current password" type="password"
                :error="form.errors.current_password" required autocomplete="current-password" @blur="touch('current_password')"
            />
            <Input
                v-model="form.password" name="password" label="New password" type="password" hint="At least 8 characters."
                :error="form.errors.password" required autocomplete="new-password" @blur="touch('password')"
            />
            <Input
                v-model="form.password_confirmation" name="password_confirmation" label="Confirm new password" type="password"
                :error="form.errors.password_confirmation" required autocomplete="new-password" @blur="touch('password_confirmation')"
            />

            <div class="flex items-center gap-4">
                <Button type="submit" :loading="form.processing">Update password</Button>
                <Transition enter-active-class="transition" enter-from-class="opacity-0" leave-active-class="transition" leave-to-class="opacity-0">
                    <p v-if="form.recentlySuccessful" class="text-sm font-medium text-emerald-600">Saved.</p>
                </Transition>
            </div>
        </form>
    </section>
</template>
