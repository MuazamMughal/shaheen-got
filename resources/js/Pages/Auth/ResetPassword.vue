<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { EnvelopeIcon, LockClosedIcon } from '@heroicons/vue/24/outline';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import Input from '@/Components/ui/Input.vue';
import Button from '@/Components/ui/Button.vue';
import { rules as r, useValidation } from '@/Composables/useValidation';

const props = defineProps({
    email: { type: String, required: true },
    token: { type: String, required: true },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

// Mirrors NewPasswordController::store.
const { validate, touch, reset: resetValidation } = useValidation(form, {
    email: [r.required('Enter your email address.'), r.email()],
    password: [r.required('Choose a new password.'), r.min(8, 'Password must be at least 8 characters.')],
    password_confirmation: [r.required('Confirm your new password.'), r.same(() => form.password, 'Passwords do not match.')],
});

const submit = () => {
    if (!validate()) return;
    form.post(route('password.store'), {
        onFinish: () => { resetValidation(); form.reset('password', 'password_confirmation'); },
    });
};
</script>

<template>
    <Head title="Reset Password" />
    <GuestLayout title="Choose a new password" subtitle="Use at least 8 characters.">
        <form class="space-y-5" novalidate @submit.prevent="submit">
            <Input v-model="form.email" name="email" @blur="touch('email')" label="Email address" type="email" :error="form.errors.email" required autocomplete="username">
                <template #icon><EnvelopeIcon class="size-5" /></template>
            </Input>

            <Input v-model="form.password" name="password" @blur="touch('password')" label="New password" type="password" :error="form.errors.password" required autofocus autocomplete="new-password">
                <template #icon><LockClosedIcon class="size-5" /></template>
            </Input>

            <Input v-model="form.password_confirmation" name="password_confirmation" @blur="touch('password_confirmation')" label="Confirm new password" type="password" :error="form.errors.password_confirmation" required autocomplete="new-password">
                <template #icon><LockClosedIcon class="size-5" /></template>
            </Input>

            <Button type="submit" size="lg" block :loading="form.processing">Reset password</Button>
        </form>
    </GuestLayout>
</template>
