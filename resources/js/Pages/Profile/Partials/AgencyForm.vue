<script setup>
import { ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { XMarkIcon } from '@heroicons/vue/24/outline';
import Input from '@/Components/ui/Input.vue';
import Button from '@/Components/ui/Button.vue';
import ImageUpload from '@/Components/ui/ImageUpload.vue';

const props = defineProps({ agency: { type: Object, required: true } });
const loginEmail = usePage().props.auth.user.email;

const form = useForm({
    agency_name: props.agency.agency_name, city: props.agency.city, address: props.agency.address,
    phone: props.agency.phone, whatsapp: props.agency.whatsapp,
    notification_emails: [...props.agency.notification_emails],
    logo: null, remove_logo: false,
});

const newEmail = ref('');
function addEmail() {
    const e = newEmail.value.trim().toLowerCase();
    if (e && e !== loginEmail && !form.notification_emails.includes(e)) form.notification_emails.push(e);
    newEmail.value = '';
}
const emailErrors = () => Object.entries(form.errors).filter(([k]) => k.startsWith('notification_emails')).map(([, v]) => v);

function submit() {
    if (newEmail.value.trim()) addEmail();
    form.post(route('agent.agency.update'), {
        preserveScroll: true, forceFormData: true,
        onSuccess: () => form.reset('logo', 'remove_logo'),
    });
}
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-semibold">Agency profile</h2>
            <p class="mt-1 text-sm text-slate-500">Shown to our team on every booking you make.</p>
        </header>

        <form class="mt-6 grid gap-5 sm:grid-cols-[1fr_180px]" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <Input v-model="form.agency_name" label="Agency name" :error="form.errors.agency_name" required class="sm:col-span-2" />
                <Input v-model="form.city" label="City" :error="form.errors.city" required />
                <Input v-model="form.phone" label="Phone" type="tel" :error="form.errors.phone" required />
                <Input v-model="form.whatsapp" label="WhatsApp" type="tel" placeholder="923xxxxxxxxx" :error="form.errors.whatsapp" />
                <Input v-model="form.address" label="Address" :error="form.errors.address" />
            </div>
            <ImageUpload
                v-model="form.logo" label="Agency logo" :current="agency.logo_url" :removed="form.remove_logo" :error="form.errors.logo"
                aspect="aspect-square" fit="object-contain" hint="Square, max 2 MB" @remove="form.remove_logo = true"
            />

            <div class="sm:col-span-2">
                <p class="mb-1.5 text-sm font-medium text-slate-700">Booking notification emails</p>
                <p class="mb-2 text-xs text-slate-500">Booking emails always go to <strong>{{ loginEmail }}</strong>. Add up to 5 more (e.g. your accounts team).</p>
                <div class="flex flex-wrap gap-2 rounded-xl border border-slate-200 bg-white p-2 focus-within:border-secondary-400 focus-within:ring-4 focus-within:ring-secondary-100">
                    <span v-for="(email, i) in form.notification_emails" :key="email" class="inline-flex items-center gap-1 rounded-lg bg-secondary-50 py-1 pl-2.5 pr-1 text-sm text-secondary">
                        {{ email }}
                        <button type="button" class="rounded p-0.5 hover:bg-secondary-100" :aria-label="`Remove ${email}`" @click="form.notification_emails.splice(i, 1)"><XMarkIcon class="size-3.5" /></button>
                    </span>
                    <input
                        v-model="newEmail" type="email" placeholder="Add email and press Enter" aria-label="Add notification email"
                        class="min-w-48 flex-1 border-0 bg-transparent px-1 py-1 text-sm focus:ring-0"
                        @keydown.enter.prevent="addEmail" @blur="addEmail"
                    />
                </div>
                <p v-for="err in emailErrors()" :key="err" class="mt-1.5 text-xs font-medium text-rose-600">{{ err }}</p>
            </div>

            <div class="sm:col-span-2">
                <Button type="submit" :loading="form.processing">Save agency profile</Button>
            </div>
        </form>
    </section>
</template>
