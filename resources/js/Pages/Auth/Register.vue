<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { BuildingOffice2Icon, UserIcon, LockClosedIcon } from '@heroicons/vue/24/outline';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import Input from '@/Components/ui/Input.vue';
import Button from '@/Components/ui/Button.vue';
import Checkbox from '@/Components/Checkbox.vue';

const form = useForm({
    agency_name: '', name: '', city: '', address: '', phone: '', whatsapp: '',
    email: '', password: '', password_confirmation: '', terms: false,
});

const submit = () => form.post(route('register'), { onFinish: () => form.reset('password', 'password_confirmation') });
</script>

<template>
    <Head title="Agent Registration" />
    <GuestLayout wide title="Register your agency" subtitle="Our team reviews every new agency. You can book group seats as soon as your account is approved.">
        <form class="space-y-6" @submit.prevent="submit">
            <fieldset class="card p-5 sm:p-6">
                <legend class="sr-only">Agency</legend>
                <p class="mb-4 flex items-center gap-2 text-sm font-semibold text-secondary"><BuildingOffice2Icon class="size-5 text-tertiary-600" /> Agency details</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <Input v-model="form.agency_name" label="Agency name" :error="form.errors.agency_name" required autofocus class="sm:col-span-2" autocomplete="organization" />
                    <Input v-model="form.city" label="City" :error="form.errors.city" required autocomplete="address-level2" />
                    <Input v-model="form.address" label="Office address" :error="form.errors.address" autocomplete="street-address" />
                </div>
            </fieldset>

            <fieldset class="card p-5 sm:p-6">
                <legend class="sr-only">Contact</legend>
                <p class="mb-4 flex items-center gap-2 text-sm font-semibold text-secondary"><UserIcon class="size-5 text-tertiary-600" /> Owner / contact person</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <Input v-model="form.name" label="Full name" :error="form.errors.name" required autocomplete="name" class="sm:col-span-2" />
                    <Input v-model="form.phone" label="Phone" type="tel" placeholder="03xx xxxxxxx" :error="form.errors.phone" required autocomplete="tel" />
                    <Input v-model="form.whatsapp" label="WhatsApp" type="tel" placeholder="923xxxxxxxxx" :error="form.errors.whatsapp" hint="With country code" />
                </div>
            </fieldset>

            <fieldset class="card p-5 sm:p-6">
                <legend class="sr-only">Login</legend>
                <p class="mb-4 flex items-center gap-2 text-sm font-semibold text-secondary"><LockClosedIcon class="size-5 text-tertiary-600" /> Login details</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <Input v-model="form.email" label="Email address" type="email" :error="form.errors.email" required autocomplete="username" class="sm:col-span-2" hint="Booking emails are sent here." />
                    <Input v-model="form.password" label="Password" type="password" :error="form.errors.password" required autocomplete="new-password" />
                    <Input v-model="form.password_confirmation" label="Confirm password" type="password" required autocomplete="new-password" />
                </div>
            </fieldset>

            <div>
                <label class="flex items-start gap-2.5 text-sm text-slate-600">
                    <Checkbox v-model:checked="form.terms" class="mt-0.5" />
                    I confirm these details are correct and I represent this travel agency.
                </label>
                <p v-if="form.errors.terms" class="mt-1.5 text-xs font-medium text-rose-600">{{ form.errors.terms }}</p>
            </div>

            <Button type="submit" size="lg" block :loading="form.processing">Submit registration</Button>
            <p class="text-center text-sm text-slate-500">
                Already registered?
                <Link :href="route('login')" class="font-semibold text-secondary hover:text-tertiary-600">Log in</Link>
            </p>
        </form>
    </GuestLayout>
</template>
