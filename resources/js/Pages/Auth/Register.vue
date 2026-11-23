<script setup>
import { nextTick, ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { BuildingOffice2Icon, UserIcon, LockClosedIcon, CheckIcon, ArrowLeftIcon, ArrowRightIcon } from '@heroicons/vue/24/outline';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import Input from '@/Components/ui/Input.vue';
import Button from '@/Components/ui/Button.vue';
import Checkbox from '@/Components/Checkbox.vue';
import { rules as r, useValidation } from '@/Composables/useValidation';

const form = useForm({
    agency_name: '', name: '', city: '', address: '', phone: '', whatsapp: '',
    email: '', password: '', password_confirmation: '', terms: false,
});

// Mirrors RegisteredUserController::store.
const { validate, touch, reset: resetValidation } = useValidation(form, {
    agency_name: [r.required('Enter your agency name.'), r.max(160)],
    city: [r.required('Enter your city.'), r.max(80)],
    address: [r.max(500)],
    name: [r.required('Enter the contact person’s full name.'), r.max(120)],
    phone: [r.required('Enter a phone number.'), r.pattern(/^[0-9+\-\s()]{7,30}$/, 'Enter a valid phone number.')],
    whatsapp: [r.digits(10, 15, 'Enter a WhatsApp number with country code (10–15 digits).')],
    email: [r.required('Enter your email address.'), r.email(), r.max(255)],
    password: [r.required('Choose a password.'), r.min(8, 'Password must be at least 8 characters.')],
    password_confirmation: [r.required('Confirm your password.'), r.same(() => form.password, 'Passwords do not match.')],
    terms: [r.accepted('Please confirm that your details are correct.')],
});

const steps = [
    { title: 'Agency', icon: BuildingOffice2Icon, fields: ['agency_name', 'city', 'address'] },
    { title: 'Contact', icon: UserIcon, fields: ['name', 'phone', 'whatsapp'] },
    { title: 'Login', icon: LockClosedIcon, fields: ['email', 'password', 'password_confirmation', 'terms'] },
];

const current = ref(0);
const stepEls = ref([]);
const isLast = () => current.value === steps.length - 1;

// Focus the first control of the step being shown.
const focusStep = () => nextTick(() => stepEls.value[current.value]?.querySelector('input')?.focus());

// Validate only the visible step; focus its first invalid field.
const stepValid = () => {
    const fields = steps[current.value].fields;
    if (validate(fields)) return true;
    const bad = fields.find((f) => form.errors[f]);
    nextTick(() => stepEls.value[current.value]?.querySelector(`[name="${bad}"]`)?.focus());
    return false;
};

const next = () => {
    if (!stepValid()) return;
    current.value++;
    focusStep();
};

const back = () => {
    current.value--;
    focusStep();
};

const goTo = (i) => {
    if (i < current.value) { current.value = i; focusStep(); }
};

const submit = () => {
    if (!isLast()) return next();
    // Re-check every step: jumping back via the stepper could have left an earlier one invalid.
    if (!validate()) return;
    form.post(route('register'), {
        onFinish: () => { resetValidation(); form.reset('password', 'password_confirmation'); },
    });
};

// Server-side errors: jump back to the first step that has one.
watch(() => ({ ...form.errors }), (errors) => {
    const i = steps.findIndex((s) => s.fields.some((f) => errors[f]));
    if (i !== -1 && i !== current.value) { current.value = i; focusStep(); }
});

const stepHasError = (i) => steps[i].fields.some((f) => form.errors[f]);
</script>

<template>
    <Head title="Agent Registration" />
    <GuestLayout wide title="Register your agency" subtitle="Our team reviews every new agency. You can book group seats as soon as your account is approved.">
        <!-- Stepper -->
        <ol class="mb-6 flex items-center" aria-label="Registration steps">
            <li v-for="(step, i) in steps" :key="step.title" class="flex items-center" :class="i < steps.length - 1 ? 'flex-1' : ''">
                <button
                    type="button"
                    class="flex items-center gap-2 text-sm font-semibold transition"
                    :class="i < current ? 'cursor-pointer text-secondary hover:text-tertiary-600' : 'cursor-default'"
                    :aria-current="i === current ? 'step' : undefined"
                    @click="goTo(i)"
                >
                    <span
                        class="flex size-8 shrink-0 items-center justify-center rounded-full text-xs ring-2 transition"
                        :class="stepHasError(i)
                            ? 'bg-rose-50 text-rose-600 ring-rose-300'
                            : i < current
                                ? 'bg-secondary text-white ring-secondary'
                                : i === current
                                    ? 'bg-tertiary text-secondary-900 ring-tertiary'
                                    : 'bg-white text-slate-400 ring-slate-200'"
                    >
                        <CheckIcon v-if="i < current && !stepHasError(i)" class="size-4" />
                        <span v-else>{{ i + 1 }}</span>
                    </span>
                    <span class="hidden sm:inline" :class="i === current ? 'text-secondary' : i < current ? '' : 'text-slate-400'">{{ step.title }}</span>
                </button>
                <span v-if="i < steps.length - 1" class="mx-3 h-0.5 flex-1 rounded-full transition" :class="i < current ? 'bg-secondary' : 'bg-slate-200'" />
            </li>
        </ol>

        <form class="space-y-6" novalidate @submit.prevent="submit">
            <fieldset v-show="current === 0" :ref="(el) => (stepEls[0] = el)" class="card p-5 sm:p-6">
                <legend class="sr-only">Agency</legend>
                <p class="mb-4 flex items-center gap-2 text-sm font-semibold text-secondary"><BuildingOffice2Icon class="size-5 text-tertiary-600" /> Agency details</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <Input v-model="form.agency_name" name="agency_name" @blur="touch('agency_name')" label="Agency name" :error="form.errors.agency_name" required autofocus class="sm:col-span-2" autocomplete="organization" />
                    <Input v-model="form.city" name="city" @blur="touch('city')" label="City" :error="form.errors.city" required autocomplete="address-level2" />
                    <Input v-model="form.address" name="address" @blur="touch('address')" label="Office address" :error="form.errors.address" autocomplete="street-address" />
                </div>
            </fieldset>

            <fieldset v-show="current === 1" :ref="(el) => (stepEls[1] = el)" class="card p-5 sm:p-6">
                <legend class="sr-only">Contact</legend>
                <p class="mb-4 flex items-center gap-2 text-sm font-semibold text-secondary"><UserIcon class="size-5 text-tertiary-600" /> Owner / contact person</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <Input v-model="form.name" name="name" @blur="touch('name')" label="Full name" :error="form.errors.name" required autocomplete="name" class="sm:col-span-2" />
                    <Input v-model="form.phone" name="phone" @blur="touch('phone')" label="Phone" type="tel" placeholder="03xx xxxxxxx" :error="form.errors.phone" required autocomplete="tel" />
                    <Input v-model="form.whatsapp" name="whatsapp" @blur="touch('whatsapp')" label="WhatsApp" type="tel" placeholder="923xxxxxxxxx" :error="form.errors.whatsapp" hint="With country code" />
                </div>
            </fieldset>

            <fieldset v-show="current === 2" :ref="(el) => (stepEls[2] = el)" class="card p-5 sm:p-6">
                <legend class="sr-only">Login</legend>
                <p class="mb-4 flex items-center gap-2 text-sm font-semibold text-secondary"><LockClosedIcon class="size-5 text-tertiary-600" /> Login details</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <Input v-model="form.email" name="email" @blur="touch('email')" label="Email address" type="email" :error="form.errors.email" required autocomplete="username" class="sm:col-span-2" hint="Booking emails are sent here." />
                    <Input v-model="form.password" name="password" @blur="touch('password')" label="Password" type="password" :error="form.errors.password" required autocomplete="new-password" />
                    <Input v-model="form.password_confirmation" name="password_confirmation" @blur="touch('password_confirmation')" label="Confirm password" type="password" :error="form.errors.password_confirmation" required autocomplete="new-password" />
                </div>

                <div class="mt-5">
                    <label class="flex items-start gap-2.5 text-sm text-slate-600">
                        <Checkbox v-model:checked="form.terms" name="terms" class="mt-0.5" />
                        I confirm these details are correct and I represent this travel agency.
                    </label>
                    <p v-if="form.errors.terms" class="mt-1.5 text-xs font-medium text-rose-600">{{ form.errors.terms }}</p>
                </div>
            </fieldset>

            <div class="flex items-center gap-3">
                <Button v-if="current > 0" variant="secondary" size="lg" @click="back">
                    <ArrowLeftIcon class="size-4" /> Back
                </Button>
                <Button v-if="!isLast()" type="submit" size="lg" class="flex-1">
                    Next <ArrowRightIcon class="size-4" />
                </Button>
                <Button v-else type="submit" size="lg" class="flex-1" :loading="form.processing">Submit registration</Button>
            </div>

            <p class="text-center text-sm text-slate-500">
                Already registered?
                <Link :href="route('login')" class="font-semibold text-secondary hover:text-tertiary-600">Log in</Link>
            </p>
        </form>
    </GuestLayout>
</template>
