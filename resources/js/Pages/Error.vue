<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Button from '@/Components/ui/Button.vue';

const props = defineProps({
    status: { type: Number, required: true },
    message: { type: String, default: null },
});

const user = computed(() => usePage().props.auth?.user);

const copy = computed(() => ({
    403: { title: 'Access denied', text: props.message ?? "Your role doesn't have permission to open this page. Ask an administrator if you need access." },
    404: { title: 'Page not found', text: "The page you're looking for doesn't exist or has been moved." },
    500: { title: 'Something went wrong', text: "We hit an unexpected error. Please try again in a moment — our team has been notified in the logs." },
    503: { title: 'Back soon', text: "We're doing some quick maintenance. Please check back in a few minutes." },
}[props.status] ?? { title: 'Error', text: 'Something went wrong.' }));
</script>

<template>
    <Head :title="copy.title" />
    <div class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden bg-secondary px-4 text-center">
        <div class="pointer-events-none absolute -right-40 -top-40 size-[34rem] rounded-full border border-tertiary/15" />
        <div class="pointer-events-none absolute -bottom-32 -left-32 size-[28rem] rounded-full bg-tertiary/10 blur-3xl" />
        <ApplicationLogo tone="light" class="relative" />
        <p class="relative mt-12 font-display text-7xl font-bold text-tertiary sm:text-8xl">{{ status }}</p>
        <h1 class="relative mt-4 font-display text-2xl font-semibold !text-white sm:text-3xl">{{ copy.title }}</h1>
        <p class="relative mt-3 max-w-md text-sm leading-relaxed text-white/70">{{ copy.text }}</p>
        <div class="relative mt-8 flex flex-col gap-3 sm:flex-row">
            <Button variant="gold" :href="user ? route('dashboard') : route('home')">{{ user ? 'Go to my dashboard' : 'Go to homepage' }}</Button>
            <button type="button" class="inline-flex h-10 items-center justify-center rounded-xl px-4 text-sm font-semibold text-white/80 ring-1 ring-white/20 hover:bg-white/10" @click="history.back()">Go back</button>
        </div>
    </div>
</template>
