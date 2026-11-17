<script setup>
import { computed, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Bars3Icon, XMarkIcon } from '@heroicons/vue/24/outline';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Button from '@/Components/ui/Button.vue';
import WhatsAppFab from '@/Components/WhatsAppFab.vue';
import FlashToaster from '@/Components/FlashToaster.vue';

defineProps({ title: { type: String, default: null } });

const page = usePage();
const user = computed(() => page.props.auth.user);
const contact = computed(() => page.props.settings?.contact ?? {});
const menuOpen = ref(false);
</script>

<template>
    <Head :title="title" />
    <FlashToaster />
    <div class="flex min-h-screen flex-col bg-primary">
        <header class="sticky top-0 z-40 border-b border-slate-200/70 bg-white/90 backdrop-blur">
            <div class="mx-auto flex h-20 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <Link :href="route('home')"><ApplicationLogo /></Link>

                <nav class="hidden items-center gap-8 text-sm font-medium text-slate-600 md:flex">
                    <Link :href="route('home')" class="hover:text-secondary">Home</Link>
                    <a href="#groups" class="hover:text-secondary">Groups</a>
                    <a href="#contact" class="hover:text-secondary">Contact</a>
                </nav>

                <div class="hidden items-center gap-3 md:flex">
                    <Button v-if="user" :href="route('dashboard')">Go to dashboard</Button>
                    <template v-else>
                        <Button variant="ghost" :href="route('login')">Agent Login</Button>
                        <Button variant="gold" :href="route('register')">Register Now</Button>
                    </template>
                </div>

                <button class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 md:hidden" :aria-label="menuOpen ? 'Close menu' : 'Open menu'" @click="menuOpen = !menuOpen">
                    <XMarkIcon v-if="menuOpen" class="size-6" />
                    <Bars3Icon v-else class="size-6" />
                </button>
            </div>

            <div v-if="menuOpen" class="border-t border-slate-100 bg-white px-4 py-4 md:hidden">
                <nav class="flex flex-col gap-1 text-sm font-medium text-slate-700">
                    <Link :href="route('home')" class="rounded-lg px-3 py-2 hover:bg-slate-50">Home</Link>
                    <a href="#groups" class="rounded-lg px-3 py-2 hover:bg-slate-50" @click="menuOpen = false">Groups</a>
                    <a href="#contact" class="rounded-lg px-3 py-2 hover:bg-slate-50" @click="menuOpen = false">Contact</a>
                </nav>
                <div class="mt-4 grid gap-2">
                    <Button v-if="user" :href="route('dashboard')" block>Go to dashboard</Button>
                    <template v-else>
                        <Button variant="secondary" :href="route('login')" block>Agent Login</Button>
                        <Button variant="gold" :href="route('register')" block>Register Now</Button>
                    </template>
                </div>
            </div>
        </header>

        <main class="flex-1"><slot /></main>

        <footer id="contact" class="bg-secondary text-white/70">
            <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-3 lg:px-8">
                <div>
                    <ApplicationLogo tone="light" />
                    <p class="mt-4 max-w-xs text-sm">Trusted group ticketing partner for travel agents across Pakistan.</p>
                </div>
                <div>
                    <p class="font-display text-sm font-semibold text-tertiary">Contact</p>
                    <ul class="mt-4 space-y-2 text-sm">
                        <li v-if="contact.phone">Phone: {{ contact.phone }}</li>
                        <li v-if="contact.whatsapp">WhatsApp: {{ contact.whatsapp }}</li>
                        <li v-if="contact.email">Email: {{ contact.email }}</li>
                        <li v-if="!contact.phone && !contact.whatsapp && !contact.email" class="text-white/40">Contact details are set in Admin → Settings.</li>
                    </ul>
                </div>
                <div>
                    <p class="font-display text-sm font-semibold text-tertiary">For agents</p>
                    <ul class="mt-4 space-y-2 text-sm">
                        <li><Link :href="route('login')" class="hover:text-white">Agent Login</Link></li>
                        <li><Link :href="route('register')" class="hover:text-white">Register your agency</Link></li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-white/10 py-5 text-center text-xs text-white/40">
                © {{ new Date().getFullYear() }} Shaheen Group of Travels. All rights reserved.
            </div>
        </footer>

        <WhatsAppFab />
    </div>
</template>
