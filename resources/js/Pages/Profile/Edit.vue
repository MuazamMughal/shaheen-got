<script setup>
import { computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AgentLayout from '@/Layouts/AgentLayout.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import AgencyForm from './Partials/AgencyForm.vue';
import { usePage } from '@inertiajs/vue3';

// Profile is shared by both sides; pick the matching shell.
const Layout = computed(() => (usePage().props.auth.user.type === 'agent' ? AgentLayout : AdminLayout));

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    agency: { type: Object, default: null },
});
</script>

<template>
        <component :is="Layout" title="My Profile">

        <div class="max-w-4xl space-y-6">
            <div v-if="agency" class="card p-6 sm:p-8">
                <AgencyForm :agency="agency" />
            </div>
            <div class="card p-6 sm:p-8">
                <UpdateProfileInformationForm :must-verify-email="mustVerifyEmail" :status="status" class="max-w-xl" />
            </div>
            <div class="card p-6 sm:p-8">
                <UpdatePasswordForm class="max-w-xl" />
            </div>
        </div>
    </component>
</template>
