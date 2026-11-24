<script setup>
import { computed, ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { PaintBrushIcon, BuildingStorefrontIcon, PhoneIcon, BellAlertIcon, EnvelopeIcon, XMarkIcon, ArrowPathIcon, PaperAirplaneIcon } from '@heroicons/vue/24/outline';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import ImageUpload from '@/Components/ui/ImageUpload.vue';
import Badge from '@/Components/ui/Badge.vue';
import { focusFirstError, rules as r, useValidation } from '@/Composables/useValidation';

const props = defineProps({
    values: { type: Object, required: true },
    files: { type: Object, required: true },
    defaults: { type: Object, required: true },
});

const form = useForm({
    ...props.values,
    admin_emails: [...props.values.admin_emails],
    logo: null, favicon: null, remove_logo: false, remove_favicon: false,
});

const colors = [
    { key: 'theme_primary', label: 'Primary', help: 'Light page background' },
    { key: 'theme_secondary', label: 'Secondary', help: 'Navy — headers, sidebar, buttons' },
    { key: 'theme_tertiary', label: 'Tertiary', help: 'Gold — accents and highlights' },
];
const HEX = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i;
const safe = (v, fallback) => (HEX.test(v ?? '') ? v : fallback);

// Scoped CSS variables: the preview restyles live without touching the rest of the page.
const previewStyle = computed(() => ({
    '--brand-primary': safe(form.theme_primary, props.defaults.theme_primary),
    '--brand-secondary': safe(form.theme_secondary, props.defaults.theme_secondary),
    '--brand-tertiary': safe(form.theme_tertiary, props.defaults.theme_tertiary),
}));

function resetColors() {
    form.theme_primary = props.defaults.theme_primary;
    form.theme_secondary = props.defaults.theme_secondary;
    form.theme_tertiary = props.defaults.theme_tertiary;
}

// Admin notification emails as chips.
const newEmail = ref('');
function addEmail() {
    const email = newEmail.value.trim().toLowerCase();
    if (email && !form.admin_emails.includes(email)) form.admin_emails.push(email);
    newEmail.value = '';
}
const removeEmail = (i) => form.admin_emails.splice(i, 1);
const emailErrors = computed(() => [...new Set(Object.entries(form.errors).filter(([k]) => k.startsWith('admin_emails')).map(([, v]) => v))]);

const hex = (label) => [r.required(`Choose a ${label} color.`), r.pattern(HEX, 'Use a hex color like #0B1B4D.')];
const { validate } = useValidation(form, {
    theme_primary: hex('primary'),
    theme_secondary: hex('secondary'),
    theme_tertiary: hex('tertiary'),
    site_name: [r.required('Enter the site name.'), r.max(120)],
    site_tagline: [r.max(160)],
    site_ticker: [r.max(500)],
    contact_whatsapp: [r.pattern(/^\+?[0-9 ]{8,20}$/, 'Enter 8–20 digits with the country code, e.g. 923001234567.')],
    contact_phone: [r.max(40)],
    contact_email: [r.email(), r.max(255)],
    contact_address: [r.max(500)],
    admin_emails: [r.maxItems(10, 'You can add up to 10 notification emails.'), r.each(r.email(), (e) => `"${e}" is not a valid email address.`)],
    low_seat_threshold: [r.required('Enter a number of seats.'), r.integer({ min: 0, max: 500 }, 'Enter a whole number from 0 to 500.')],
});

function submit() {
    if (newEmail.value.trim()) addEmail();
    if (!validate()) return;
    form.post(route('admin.settings.update'), {
        preserveScroll: true,
        onError: () => focusFirstError(),
        forceFormData: true,
        onSuccess: () => form.reset('logo', 'favicon', 'remove_logo', 'remove_favicon'),
    });
}

const testForm = useForm({ email: usePage().props.auth.user.email });
const { validate: validateTest } = useValidation(testForm, { email: [r.required('Enter an email address.'), r.email()] });
const sendTest = () => validateTest() && testForm.post(route('admin.settings.test-email'), { preserveScroll: true });
</script>

<template>
    <AdminLayout title="Settings" subtitle="Branding, contact details and notifications">
        <form class="mx-auto max-w-5xl space-y-6 pb-24" novalidate @submit.prevent="submit">
            <!-- Appearance -->
            <section class="card overflow-hidden">
                <header class="flex items-center justify-between gap-4 border-b border-slate-100 px-6 py-4">
                    <div class="flex items-center gap-3">
                        <span class="grid size-9 place-items-center rounded-lg bg-secondary-50 text-secondary"><PaintBrushIcon class="size-5" /></span>
                        <div>
                            <h2 class="font-semibold">Theme colors</h2>
                            <p class="text-xs text-slate-500">Applied to the whole app after saving — no rebuild needed.</p>
                        </div>
                    </div>
                    <Button variant="ghost" size="sm" @click="resetColors"><ArrowPathIcon class="size-4" /> Reset to logo colors</Button>
                </header>
                <div class="grid gap-6 p-6 lg:grid-cols-[1fr_1.1fr]">
                    <div class="space-y-4">
                        <div v-for="c in colors" :key="c.key" class="flex items-center gap-4">
                            <label class="relative size-12 shrink-0 cursor-pointer overflow-hidden rounded-xl ring-1 ring-slate-200" :style="{ background: safe(form[c.key], '#fff') }">
                                <input v-model="form[c.key]" type="color" class="absolute inset-0 size-full cursor-pointer opacity-0" :aria-label="`${c.label} color picker`" />
                            </label>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-slate-800">{{ c.label }}</p>
                                <p class="text-xs text-slate-500">{{ c.help }}</p>
                            </div>
                            <Input v-model="form[c.key]" :error="form.errors[c.key]" class="w-32 [&_input]:font-mono [&_input]:uppercase" maxlength="7" :aria-label="`${c.label} hex value`" />
                        </div>
                    </div>

                    <!-- Live preview -->
                    <div :style="previewStyle" class="overflow-hidden rounded-2xl border border-slate-200 bg-primary" aria-label="Theme preview">
                        <div class="flex items-center justify-between bg-secondary px-4 py-3">
                            <span class="font-display text-sm font-bold tracking-[0.18em] text-tertiary">SHAHEEN</span>
                            <span class="size-6 rounded-md bg-tertiary/20 ring-1 ring-tertiary/50" />
                        </div>
                        <div class="space-y-3 p-4">
                            <div class="rounded-xl bg-white p-4 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-wider text-tertiary-600">Umrah Groups</p>
                                <p class="mt-1 font-display font-semibold text-secondary">LAHORE-JEDDAH-LAHORE</p>
                                <div class="mt-3 flex items-center justify-between">
                                    <span class="font-display text-lg font-bold text-secondary">PKR 182,000</span>
                                    <span class="rounded-lg bg-secondary px-3 py-1.5 text-xs font-semibold text-white">Book Now</span>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <span class="rounded-full bg-secondary px-3 py-1 text-xs font-semibold text-white">All</span>
                                <span class="rounded-full bg-tertiary px-3 py-1 text-xs font-semibold text-secondary-900">Gold button</span>
                                <span class="rounded-full bg-secondary-50 px-3 py-1 text-xs font-semibold text-secondary">Badge</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Branding -->
            <section class="card p-6">
                <div class="mb-5 flex items-center gap-3">
                    <span class="grid size-9 place-items-center rounded-lg bg-secondary-50 text-secondary"><BuildingStorefrontIcon class="size-5" /></span>
                    <h2 class="font-semibold">Branding</h2>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <Input v-model="form.site_name" label="Site name" :error="form.errors.site_name" required />
                    <Input v-model="form.site_tagline" label="Tagline" :error="form.errors.site_tagline" />
                    <ImageUpload
                        v-model="form.logo" label="Logo" :current="files.logo" :removed="form.remove_logo" :error="form.errors.logo"
                        aspect="aspect-[2/1]" fit="object-contain" accept="image/png,image/jpeg,image/webp"
                        hint="Square works best, max 2 MB. Leave empty to use the default logo." @remove="form.remove_logo = true"
                    />
                    <ImageUpload
                        v-model="form.favicon" label="Favicon" :current="files.favicon" :removed="form.remove_favicon" :error="form.errors.favicon" :max-kb="512"
                        aspect="aspect-[2/1]" fit="object-contain" accept="image/png,image/x-icon,.ico"
                        hint="PNG or ICO, max 512 KB" @remove="form.remove_favicon = true"
                    />
                    <Textarea v-model="form.site_ticker" label="Announcement ticker" :rows="2" :error="form.errors.site_ticker" class="sm:col-span-2" hint="Scrolls across the top of the agent portal. Leave empty to hide it." />
                </div>
            </section>

            <!-- Contact -->
            <section class="card p-6">
                <div class="mb-5 flex items-center gap-3">
                    <span class="grid size-9 place-items-center rounded-lg bg-secondary-50 text-secondary"><PhoneIcon class="size-5" /></span>
                    <h2 class="font-semibold">Contact details</h2>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <Input v-model="form.contact_whatsapp" label="WhatsApp number" placeholder="923001234567" :error="form.errors.contact_whatsapp" hint="With country code. Used for all WhatsApp buttons." />
                    <Input v-model="form.contact_phone" label="Phone" :error="form.errors.contact_phone" />
                    <Input v-model="form.contact_email" label="Public email" type="email" :error="form.errors.contact_email" />
                    <Input v-model="form.contact_address" label="Address" :error="form.errors.contact_address" />
                </div>
            </section>

            <!-- Notifications -->
            <section class="card p-6">
                <div class="mb-5 flex items-center gap-3">
                    <span class="grid size-9 place-items-center rounded-lg bg-secondary-50 text-secondary"><BellAlertIcon class="size-5" /></span>
                    <div>
                        <h2 class="font-semibold">Notifications</h2>
                        <p class="text-xs text-slate-500">These addresses receive admin emails (new agents, bookings, sold-out flights).</p>
                    </div>
                </div>
                <div class="grid gap-5 sm:grid-cols-[1fr_220px]">
                    <div>
                        <p class="mb-1.5 text-sm font-medium text-slate-700">Admin notification emails</p>
                        <div :class="['flex flex-wrap gap-2 rounded-xl border bg-white p-2 focus-within:ring-4', emailErrors.length ? 'border-rose-300 focus-within:ring-rose-100' : 'border-slate-200 focus-within:border-secondary-400 focus-within:ring-secondary-100']">
                            <span v-for="(email, i) in form.admin_emails" :key="email" class="inline-flex items-center gap-1 rounded-lg bg-secondary-50 py-1 pl-2.5 pr-1 text-sm text-secondary">
                                {{ email }}
                                <button type="button" class="rounded p-0.5 hover:bg-secondary-100" :aria-label="`Remove ${email}`" @click="removeEmail(i)"><XMarkIcon class="size-3.5" /></button>
                            </span>
                            <input
                                v-model="newEmail" type="email" placeholder="Add email and press Enter" aria-label="Add notification email" :aria-invalid="emailErrors.length > 0"
                                class="min-w-48 flex-1 border-0 bg-transparent px-1 py-1 text-sm focus:ring-0"
                                @keydown.enter.prevent="addEmail" @keydown.,.prevent="addEmail" @blur="addEmail"
                            />
                        </div>
                        <p v-for="err in emailErrors" :key="err" class="mt-1.5 text-xs font-medium text-rose-600">{{ err }}</p>
                    </div>
                    <Input v-model="form.low_seat_threshold" label="Low-seat alert at" type="number" min="0" :error="form.errors.low_seat_threshold" hint="Alert admins when a flight has this many seats or fewer." />
                </div>
            </section>

            <!-- Test email (separate form) -->
            <section class="card p-6">
                <div class="mb-5 flex items-center gap-3">
                    <span class="grid size-9 place-items-center rounded-lg bg-secondary-50 text-secondary"><EnvelopeIcon class="size-5" /></span>
                    <div>
                        <h2 class="font-semibold">Test outgoing email</h2>
                        <p class="text-xs text-slate-500">Sends immediately using the mail settings in <code>.env</code>. Locally, mail goes to Mailpit.</p>
                    </div>
                    <Badge class="ml-auto hidden sm:inline-flex" color="slate">SMTP</Badge>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
                    <Input v-model="testForm.email" type="email" :error="testForm.errors.email" aria-label="Test email recipient" class="sm:w-80" />
                    <Button variant="secondary" :loading="testForm.processing" @click="sendTest"><PaperAirplaneIcon class="size-4" /> Send test email</Button>
                </div>
            </section>

            <div class="fixed inset-x-0 bottom-0 z-20 border-t border-slate-200 bg-white/95 backdrop-blur lg:left-72">
                <div class="mx-auto flex max-w-5xl items-center justify-end gap-3 px-4 py-3 sm:px-6">
                    <span v-if="form.isDirty" class="text-sm text-amber-700">Unsaved changes</span>
                    <Button type="submit" :loading="form.processing">Save settings</Button>
                </div>
            </div>
        </form>
    </AdminLayout>
</template>
