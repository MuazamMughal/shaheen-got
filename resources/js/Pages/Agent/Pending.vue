<script setup>
import { Link } from '@inertiajs/vue3';
import { ClockIcon } from '@heroicons/vue/24/outline';
import AgentLayout from '@/Layouts/AgentLayout.vue';
import Button from '@/Components/ui/Button.vue';

defineProps({ status: { type: String, required: true } });
</script>

<template>
    <AgentLayout title="Approval pending" hide-nav>
        <div class="mx-auto mt-6 max-w-lg">
            <div class="card overflow-hidden text-center">
                <div class="bg-secondary px-6 py-10">
                    <div class="mx-auto grid size-16 place-items-center rounded-full bg-tertiary/15 text-tertiary ring-1 ring-tertiary/40">
                        <ClockIcon class="size-8" />
                    </div>
                    <h2 class="mt-5 font-display text-2xl font-semibold !text-white">Your account is under review</h2>
                </div>
                <div class="px-6 py-8">
                    <p class="text-sm leading-relaxed text-slate-600">
                        Thanks for registering, <strong class="text-slate-900">{{ $page.props.auth.user.name }}</strong>.
                        Our team will review your agency and approve your account shortly.
                        You will receive an email as soon as booking is enabled.
                    </p>
                    <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                        <Button variant="secondary" :href="route('profile.edit')">View profile</Button>
                        <Link :href="route('logout')" method="post" as="button" class="inline-flex h-10 items-center justify-center rounded-xl px-4 text-sm font-semibold text-slate-500 hover:bg-slate-100">
                            Log out
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AgentLayout>
</template>
