<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { LockClosedIcon } from '@heroicons/vue/24/outline';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import Input from '@/Components/ui/Input.vue';
import Button from '@/Components/ui/Button.vue';
import { rules as r, useValidation } from '@/Composables/useValidation';

const form = useForm({ password: '' });

const { validate, touch, reset: resetValidation } = useValidation(form, {
    password: [r.required('Enter your password.')],
});

const submit = () => {
    if (!validate()) return;
    form.post(route('password.confirm'), {
        onFinish: () => { resetValidation(); form.reset(); },
    });
};
</script>

<template>
    <Head title="Confirm Password" />
    <GuestLayout title="Confirm your password" subtitle="This is a secure area. Please confirm your password before continuing.">
        <form class="space-y-5" novalidate @submit.prevent="submit">
            <Input v-model="form.password" name="password" @blur="touch('password')" label="Password" type="password" :error="form.errors.password" required autofocus autocomplete="current-password">
                <template #icon><LockClosedIcon class="size-5" /></template>
            </Input>

            <Button type="submit" size="lg" block :loading="form.processing">Confirm</Button>
        </form>
    </GuestLayout>
</template>
