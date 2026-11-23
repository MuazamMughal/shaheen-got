<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { EnvelopeIcon } from '@heroicons/vue/24/outline';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import Input from '@/Components/ui/Input.vue';
import Button from '@/Components/ui/Button.vue';
import { rules as r, useValidation } from '@/Composables/useValidation';

defineProps({
    status: { type: String },
});

const form = useForm({ email: '' });

const { validate, touch } = useValidation(form, {
    email: [r.required('Enter your email address.'), r.email()],
});

const submit = () => validate() && form.post(route('password.email'));
</script>

<template>
    <Head title="Forgot Password" />
    <GuestLayout title="Forgot your password?" subtitle="Enter your account email and we’ll send you a link to choose a new password.">
        <div v-if="status" class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
            {{ status }}
        </div>

        <form class="space-y-5" novalidate @submit.prevent="submit">
            <Input v-model="form.email" name="email" @blur="touch('email')" label="Email address" type="email" :error="form.errors.email" required autofocus autocomplete="username" placeholder="you@agency.com">
                <template #icon><EnvelopeIcon class="size-5" /></template>
            </Input>

            <Button type="submit" size="lg" block :loading="form.processing">Email password reset link</Button>

            <p class="text-center text-sm text-slate-500">
                Remembered it?
                <Link :href="route('login')" class="font-semibold text-secondary hover:text-tertiary-600">Back to log in</Link>
            </p>
        </form>
    </GuestLayout>
</template>
