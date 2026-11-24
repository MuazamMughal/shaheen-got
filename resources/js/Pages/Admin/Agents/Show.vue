<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import BookingStatusBadge from '@/Components/bookings/BookingStatusBadge.vue';
import {
    ArrowLeftIcon, CheckIcon, XMarkIcon, NoSymbolIcon, ArrowPathIcon, PencilSquareIcon,
    PhoneIcon, EnvelopeIcon, MapPinIcon, ChatBubbleLeftRightIcon, TicketIcon,
} from '@heroicons/vue/24/outline';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Modal from '@/Components/ui/Modal.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import AgentStatusBadge from '@/Components/agents/AgentStatusBadge.vue';
import AgentActionDialog from '@/Components/agents/AgentActionDialog.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { focusFirstError, rules as r, useValidation } from '@/Composables/useValidation';
import { agencyRules } from '@/Composables/agencyRules';

const props = defineProps({
    agent: { type: Object, required: true },
    bookings: { type: Array, default: () => [] },
    bookingCount: { type: Number, default: 0 },
});
const { can } = usePermissions();

const action = ref(null);
const act = (type) => (action.value = { type, agent: props.agent });

const editing = ref(false);
const form = useForm({
    agency_name: props.agent.agency_name ?? '', name: props.agent.name, email: props.agent.email,
    phone: props.agent.phone ?? '', whatsapp: props.agent.whatsapp ?? '', city: props.agent.city ?? '', address: props.agent.address ?? '',
});
const { validate, reset: resetValidation } = useValidation(form, {
    ...agencyRules,
    name: [r.required('Enter the contact name.'), r.max(120)],
    email: [r.required('Enter the login email.'), r.email(), r.max(255)],
});
const openEdit = () => { form.reset(); form.clearErrors(); resetValidation(); editing.value = true; };
const save = () => validate() && form.put(route('admin.agents.update', props.agent.id), {
    preserveScroll: true,
    onSuccess: () => { editing.value = false; form.defaults(); },
    onError: () => focusFirstError(),
});
</script>

<template>
    <AdminLayout :title="agent.agency_name ?? agent.name" subtitle="Agent details">
        <template #actions>
            <Button variant="ghost" :href="route('admin.agents.index', { status: agent.status })" class="hidden sm:inline-flex"><ArrowLeftIcon class="size-4" /> All agents</Button>
        </template>

        <div class="mx-auto grid max-w-6xl gap-6 lg:grid-cols-[360px_1fr]">
            <!-- Profile card -->
            <aside class="space-y-6">
                <div class="card overflow-hidden">
                    <div class="relative bg-secondary px-6 pb-14 pt-6">
                        <div class="pointer-events-none absolute -right-10 -top-10 size-40 rounded-full border border-tertiary/25" />
                        <AgentStatusBadge :status="agent.status" :label="agent.status_label" />
                    </div>
                    <div class="-mt-10 px-6 pb-6">
                        <span class="grid size-20 place-items-center overflow-hidden rounded-2xl border-4 border-white bg-tertiary font-display text-2xl font-bold text-secondary shadow">
                            <img v-if="agent.logo_url" :src="agent.logo_url" alt="" class="size-full object-cover" />
                            <template v-else>{{ (agent.agency_name ?? agent.name).slice(0, 1) }}</template>
                        </span>
                        <h2 class="mt-3 font-display text-xl font-semibold">{{ agent.agency_name ?? '—' }}</h2>
                        <p class="text-sm text-slate-500">{{ agent.name }}</p>

                        <ul class="mt-5 space-y-3 text-sm">
                            <li class="flex items-center gap-3"><EnvelopeIcon class="size-5 text-slate-400" /><a :href="`mailto:${agent.email}`" class="truncate text-slate-700 hover:text-secondary">{{ agent.email }}</a></li>
                            <li class="flex items-center gap-3"><PhoneIcon class="size-5 text-slate-400" /><a :href="`tel:${agent.phone}`" class="text-slate-700 hover:text-secondary">{{ agent.phone ?? '—' }}</a></li>
                            <li v-if="agent.whatsapp" class="flex items-center gap-3"><ChatBubbleLeftRightIcon class="size-5 text-emerald-500" /><a :href="`https://wa.me/${agent.whatsapp}`" target="_blank" rel="noopener" class="text-slate-700 hover:text-secondary">{{ agent.whatsapp }}</a></li>
                            <li class="flex items-start gap-3"><MapPinIcon class="size-5 shrink-0 text-slate-400" /><span class="text-slate-700">{{ [agent.address, agent.city].filter(Boolean).join(', ') || '—' }}</span></li>
                        </ul>

                        <div v-if="agent.status_reason" class="mt-5 rounded-xl border border-rose-100 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                            <p class="font-semibold">Reason</p>
                            <p class="mt-0.5">{{ agent.status_reason }}</p>
                        </div>

                        <div class="mt-6 grid gap-2">
                            <template v-if="can('agents.approve')">
                                <Button v-if="['pending', 'rejected'].includes(agent.status)" variant="success" block @click="act('approve')"><CheckIcon class="size-4" /> Approve agent</Button>
                                <Button v-if="agent.status === 'pending'" variant="secondary" block class="!text-rose-600" @click="act('reject')"><XMarkIcon class="size-4" /> Reject</Button>
                            </template>
                            <template v-if="can('agents.suspend')">
                                <Button v-if="agent.status === 'approved'" variant="secondary" block class="!text-rose-600" @click="act('suspend')"><NoSymbolIcon class="size-4" /> Suspend</Button>
                                <Button v-if="agent.status === 'suspended'" variant="success" block @click="act('reactivate')"><ArrowPathIcon class="size-4" /> Reactivate</Button>
                            </template>
                            <Button v-if="can('agents.edit')" variant="ghost" block @click="openEdit"><PencilSquareIcon class="size-4" /> Edit details</Button>
                        </div>
                    </div>
                </div>
            </aside>

            <div class="space-y-6">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="card p-5"><p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Registered</p><p class="mt-1 font-semibold text-slate-900">{{ agent.registered_at }}</p></div>
                    <div class="card p-5"><p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Approved</p><p class="mt-1 font-semibold text-slate-900">{{ agent.approved_at ?? '—' }}</p><p v-if="agent.approved_by" class="text-xs text-slate-500">by {{ agent.approved_by }}</p></div>
                    <div class="card p-5"><p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Last login</p><p class="mt-1 font-semibold text-slate-900">{{ agent.last_login_at ?? 'Never' }}</p></div>
                </div>

                <div class="card p-6">
                    <h3 class="font-semibold">Notification emails</h3>
                    <p class="mt-1 text-sm text-slate-500">Booking emails go to the login email plus these addresses.</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <span class="rounded-lg bg-secondary-50 px-2.5 py-1 text-sm text-secondary">{{ agent.email }}</span>
                        <span v-for="e in agent.notification_emails" :key="e" class="rounded-lg bg-slate-100 px-2.5 py-1 text-sm text-slate-700">{{ e }}</span>
                    </div>
                </div>

                <div class="card overflow-hidden">
                    <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                        <h3 class="font-semibold">Bookings <span class="text-sm font-normal text-slate-500">({{ bookingCount }})</span></h3>
                        <Link v-if="bookingCount" :href="route('admin.bookings.index', { search: agent.agency_name, status: '' })" class="text-sm font-semibold text-secondary hover:text-tertiary-600">View all</Link>
                    </div>
                    <ul v-if="bookings.length" class="divide-y divide-slate-100">
                        <li v-for="b in bookings" :key="b.id">
                            <Link :href="route('admin.bookings.show', b.id)" class="flex flex-wrap items-center gap-x-4 gap-y-1 px-6 py-3 transition hover:bg-tertiary-50/40">
                                <span class="w-24 font-semibold text-secondary">{{ b.booking_no }}</span>
                                <span class="min-w-0 flex-1 truncate text-sm text-slate-700">{{ b.flight.sector }} · {{ b.flight.departure_date_label }}</span>
                                <span class="text-xs text-slate-500">{{ b.total_pax }} pax</span>
                                <BookingStatusBadge :booking="b" />
                            </Link>
                        </li>
                    </ul>
                    <EmptyState v-else title="No bookings yet" description="This agency's bookings will be listed here.">
                        <template #icon><TicketIcon class="size-7" /></template>
                    </EmptyState>
                </div>
            </div>
        </div>

        <AgentActionDialog :action="action" @close="action = null" />

        <Modal :show="editing" max-width="2xl" title="Edit agent details" @close="editing = false">
            <form id="agent-form" class="grid gap-4 sm:grid-cols-2" novalidate @submit.prevent="save">
                <Input v-model="form.agency_name" label="Agency name" :error="form.errors.agency_name" required class="sm:col-span-2" />
                <Input v-model="form.name" label="Contact name" :error="form.errors.name" required />
                <Input v-model="form.email" label="Login email" type="email" :error="form.errors.email" required />
                <Input v-model="form.phone" label="Phone" type="tel" :error="form.errors.phone" required />
                <Input v-model="form.whatsapp" label="WhatsApp" type="tel" placeholder="923xxxxxxxxx" :error="form.errors.whatsapp" />
                <Input v-model="form.city" label="City" :error="form.errors.city" required />
                <Input v-model="form.address" label="Address" :error="form.errors.address" />
            </form>
            <template #footer>
                <Button variant="secondary" @click="editing = false">Cancel</Button>
                <Button type="submit" form="agent-form" :loading="form.processing">Save</Button>
            </template>
        </Modal>
    </AdminLayout>
</template>
