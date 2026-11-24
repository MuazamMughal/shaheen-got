<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';
import Input from '@/Components/ui/Input.vue';
import Button from '@/Components/ui/Button.vue';
import { focusFirstError, rules as r, useValidation } from '@/Composables/useValidation';

defineProps({
    mustVerifyEmail: { type: Boolean },
    status: { type: String },
});

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
});

// Mirrors ProfileUpdateRequest.
const { validate, touch } = useValidation(form, {
    name: [r.required('Enter your name.'), r.max(255)],
    email: [r.required('Enter your email address.'), r.email(), r.max(255)],
});

const submit = () => validate() && form.patch(route('profile.update'), { preserveScroll: true, onError: () => focusFirstError() });
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-semibold">Profile information</h2>
            <p class="mt-1 text-sm text-slate-500">Update your name and the email address you sign in with.</p>
        </header>

        <form class="mt-6 max-w-xl space-y-5" novalidate @submit.prevent="submit">
            <Input v-model="form.name" name="name" label="Name" :error="form.errors.name" required autocomplete="name" @blur="touch('name')" />
            <Input v-model="form.email" name="email" label="Email" type="email" :error="form.errors.email" required autocomplete="username" @blur="touch('email')" />

            <div v-if="mustVerifyEmail && user.email_verified_at === null" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Your email address is unverified.
                <Link :href="route('verification.send')" method="post" as="button" class="font-semibold underline hover:text-amber-900">
                    Re-send the verification email.
                </Link>
                <p v-show="status === 'verification-link-sent'" class="mt-2 font-medium text-emerald-700">
                    A new verification link has been sent to your email address.
                </p>
            </div>

            <div class="flex items-center gap-4">
                <Button type="submit" :loading="form.processing">Save</Button>
                <Transition enter-active-class="transition" enter-from-class="opacity-0" leave-active-class="transition" leave-to-class="opacity-0">
                    <p v-if="form.recentlySuccessful" class="text-sm font-medium text-emerald-600">Saved.</p>
                </Transition>
            </div>
        </form>
    </section>
</template>
