<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { PaperAirplaneIcon, ShieldCheckIcon, BoltIcon, ChatBubbleLeftRightIcon, ArrowRightIcon, LockClosedIcon } from '@heroicons/vue/24/outline';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Button from '@/Components/ui/Button.vue';
import CategoryCard from '@/Components/CategoryCard.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const isAdminSide = computed(() => ['super_admin', 'staff'].includes(user.value?.type));
const can = (perm) => (page.props.auth.permissions ?? []).includes(perm);

/*
 * Where a group card leads depends on who is looking:
 *  - guests: the agent group page; the auth middleware sends them to log in
 *    and Laravel's "intended" redirect brings them back to this group after.
 *  - agents: that group's fares (pending agents get bounced to their pending page).
 *  - admins/staff: the flights list filtered to this group (or categories, or nothing
 *    when they lack both permissions).
 */
const cardLink = (c) => {
    if (isAdminSide.value) {
        if (can('flights.view')) return { href: route('admin.flights.index', { category: c.id }), cta: 'Manage flights' };
        if (can('categories.manage')) return { href: route('admin.categories.index'), cta: 'Manage groups' };
        return null;
    }
    return { href: route('agent.groups.show', c.slug), cta: user.value ? 'View fares' : 'Log in to view fares' };
};

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
                <template v-for="c in categories" :key="c.id">
                    <Link
                        v-if="cardLink(c)"
                        :href="cardLink(c).href"
                        class="rounded-2xl focus:outline-none focus-visible:ring-4 focus-visible:ring-tertiary-300"
                        :aria-label="`${c.name}: ${cardLink(c).cta}`"
                    >
                        <CategoryCard :category="c">
                            <div class="mt-auto pt-4"><div class="flex items-center justify-between border-t border-dashed border-slate-200 pt-4 text-sm font-semibold text-secondary">
                                <span class="flex items-center gap-1.5">
                                    <LockClosedIcon v-if="!user" class="size-4 text-tertiary-600" />
                                    {{ cardLink(c).cta }}
                                </span>
                                <ArrowRightIcon class="size-4 transition group-hover:translate-x-1" />
                            </div></div>
                        </CategoryCard>
                    </Link>
                    <CategoryCard v-else :category="c" />
                </template>
            </div>
            <p v-else class="mt-12 text-center text-sm text-slate-500">Group fares will be listed here soon.</p>
        </section>
    </PublicLayout>
</template>
