<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { EnvelopeIcon, LockClosedIcon } from '@heroicons/vue/24/outline';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import Checkbox from '@/Components/Checkbox.vue';
import Input from '@/Components/ui/Input.vue';
import Button from '@/Components/ui/Button.vue';

defineProps({
    canResetPassword: { type: Boolean },
    status: { type: String },
});

const form = useForm({ email: '', password: '', remember: false });

const submit = () => form.post(route('login'), { onFinish: () => form.reset('password') });
</script>

<template>
    <Head title="Log in" />
    <GuestLayout title="Welcome back" subtitle="Sign in to your agent or admin account.">
        <div v-if="status" class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
            {{ status }}
        </div>

        <form class="space-y-5" @submit.prevent="submit">
            <Input v-model="form.email" label="Email address" type="email" :error="form.errors.email" required autofocus autocomplete="username" placeholder="you@agency.com">
                <template #icon><EnvelopeIcon class="size-5" /></template>
            </Input>

            <Input v-model="form.password" label="Password" type="password" :error="form.errors.password" required autocomplete="current-password" placeholder="••••••••">
                <template #icon><LockClosedIcon class="size-5" /></template>
            </Input>

            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <Checkbox v-model:checked="form.remember" name="remember" /> Remember me
                </label>
                <Link v-if="canResetPassword" :href="route('password.request')" class="text-sm font-medium text-secondary hover:text-tertiary-600">
                    Forgot password?
                </Link>
            </div>

            <Button type="submit" size="lg" block :loading="form.processing">Log in</Button>
        </form>

        <div class="mt-8 rounded-2xl border border-tertiary-200 bg-tertiary-50 p-5 text-center">
            <p class="text-sm text-slate-600">New travel agency?</p>
            <Link :href="route('register')" class="mt-1 inline-block font-semibold text-secondary hover:text-tertiary-600">
                Register as an agent →
            </Link>
        </div>
    </GuestLayout>
</template>
