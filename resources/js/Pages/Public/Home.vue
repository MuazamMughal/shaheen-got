<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { PaperAirplaneIcon, ShieldCheckIcon, BoltIcon, ChatBubbleLeftRightIcon } from '@heroicons/vue/24/outline';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Button from '@/Components/ui/Button.vue';
import CategoryCard from '@/Components/CategoryCard.vue';

const user = computed(() => usePage().props.auth.user);

defineProps({ categories: { type: Array, default: () => [] } });

const features = [
    { icon: BoltIcon, title: 'Live seat availability', text: 'See real-time fares and seats across every sector before you book.' },
    { icon: ShieldCheckIcon, title: 'Confirmed by our desk', text: 'Every booking is verified and confirmed by the Shaheen team.' },
    { icon: ChatBubbleLeftRightIcon, title: 'WhatsApp support', text: 'Send payment proof and get help instantly on WhatsApp.' },
];
</script>

<template>
    <PublicLayout title="Group Ticketing for Travel Agents">
        <!-- Hero -->
        <section class="relative overflow-hidden bg-secondary">
            <div class="pointer-events-none absolute -right-48 -top-48 size-[40rem] rounded-full border border-tertiary/15" />
            <div class="pointer-events-none absolute -right-28 -top-28 size-[28rem] rounded-full border border-tertiary/20" />
            <div class="pointer-events-none absolute -bottom-40 left-1/3 size-[30rem] rounded-full bg-tertiary/10 blur-3xl" />

            <div class="relative mx-auto max-w-7xl px-4 py-20 sm:px-6 sm:py-28 lg:px-8">
                <div class="max-w-2xl">
                    <span class="inline-flex items-center gap-2 rounded-full border border-tertiary/30 bg-tertiary/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-tertiary">
                        <PaperAirplaneIcon class="size-3.5" /> B2B Group Ticketing
                    </span>
                    <h1 class="mt-6 font-display text-4xl font-semibold leading-tight !text-white sm:text-5xl lg:text-6xl">
                        Your journey,<br /><span class="text-tertiary">our priority.</span>
                    </h1>
                    <p class="mt-6 max-w-xl text-base leading-relaxed text-white/70 sm:text-lg">
                        Book Umrah, KSA, UAE and international group seats at agent fares —
                        live availability, fast confirmations, one trusted partner.
                    </p>
                    <div class="mt-10 flex flex-col gap-3 sm:flex-row">
                        <Button v-if="user" variant="gold" size="lg" :href="route('dashboard')">Go to dashboard</Button>
                        <template v-else>
                            <Button variant="gold" size="lg" :href="route('register')">Register your agency</Button>
                            <Button size="lg" :href="route('login')" class="!bg-white/10 ring-1 ring-white/20 hover:!bg-white/20">Agent Login</Button>
                        </template>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features -->
        <section class="relative z-10 mx-auto -mt-10 max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-4 md:grid-cols-3">
                <div v-for="f in features" :key="f.title" class="card flex gap-4 p-6">
                    <div class="grid size-12 shrink-0 place-items-center rounded-xl bg-tertiary-50 text-tertiary-700">
                        <component :is="f.icon" class="size-6" />
                    </div>
                    <div>
                        <h3 class="font-semibold">{{ f.title }}</h3>
                        <p class="mt-1 text-sm text-slate-500">{{ f.text }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Groups -->
        <section id="groups" class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
            <div class="flex flex-col items-center text-center">
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-tertiary-600">Destinations</p>
                <h2 class="mt-3 font-display text-3xl font-semibold sm:text-4xl">Our Group Fares</h2>
                <div class="gold-rule mt-5" />
            </div>

            <div v-if="categories.length" class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <CategoryCard v-for="c in categories" :key="c.id" :category="c" />
            </div>
            <p v-else class="mt-12 text-center text-sm text-slate-500">Group fares will be listed here soon.</p>
        </section>
    </PublicLayout>
</template>
