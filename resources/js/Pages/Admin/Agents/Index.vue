<script setup>
import { reactive, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { MagnifyingGlassIcon, CheckIcon, XMarkIcon, EyeIcon, NoSymbolIcon, ArrowPathIcon, ChatBubbleLeftRightIcon } from '@heroicons/vue/24/outline';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Input from '@/Components/ui/Input.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import RowActions from '@/Components/ui/RowActions.vue';
import AgentStatusBadge from '@/Components/agents/AgentStatusBadge.vue';
import AgentActionDialog from '@/Components/agents/AgentActionDialog.vue';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({
    agents: { type: Object, required: true },
    counts: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const { can } = usePermissions();

const filters = reactive({ status: props.filters.status ?? 'pending', search: props.filters.search ?? '' });
let timer;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(route('admin.agents.index'), Object.fromEntries(Object.entries(filters).filter(([, v]) => v)), {
            preserveState: true, preserveScroll: true, replace: true,
        });
    }, 300);
});

const tabs = [
    { value: 'pending', label: 'Pending approval' },
    { value: 'approved', label: 'Approved' },
    { value: 'suspended', label: 'Suspended' },
    { value: 'rejected', label: 'Rejected' },
];

const columns = [
    { key: 'agency_name', label: 'Agency' },
    { key: 'name', label: 'Contact', sortable: true, class: 'hidden md:table-cell' },
    { key: 'city', label: 'City', class: 'hidden lg:table-cell' },
    { key: 'created_at', label: 'Registered', sortable: true, class: 'hidden xl:table-cell' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '', align: 'right' },
];

const action = ref(null);
const act = (type, agent) => (action.value = { type, agent });
const wa = (n) => `https://wa.me/${n}`;
const item = 'flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 focus:bg-slate-50 focus:outline-none';
</script>

<template>
    <AdminLayout title="Agents" subtitle="Approve new agencies and manage access">
        <div class="-mx-1 mb-4 flex gap-2 overflow-x-auto px-1 pb-1" role="tablist">
            <button
                v-for="t in tabs" :key="t.value" type="button" role="tab" :aria-selected="filters.status === t.value" @click="filters.status = t.value"
                :class="['inline-flex shrink-0 items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold transition',
                    filters.status === t.value ? 'bg-secondary text-white shadow-sm' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:ring-slate-300']"
            >
                {{ t.label }}
                <span :class="['rounded-full px-1.5 text-xs', filters.status === t.value ? 'bg-tertiary text-secondary-900' : t.value === 'pending' && counts.pending ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600']">{{ counts[t.value] }}</span>
            </button>
        </div>

        <Input v-model="filters.search" placeholder="Search agency, name, email, phone or city" aria-label="Search agents" class="mb-4 sm:max-w-md">
            <template #icon><MagnifyingGlassIcon class="size-5" /></template>
        </Input>

        <DataTable
            :columns="columns" :rows="agents" :sort="props.filters.sort ?? 'created_at'" :direction="props.filters.direction ?? 'desc'"
            :empty-title="filters.status === 'pending' ? 'No agents waiting for approval' : 'No agents here'"
            empty-description="New registrations appear in Pending approval."
        >
            <template #cell-agency_name="{ row }">
                <Link :href="route('admin.agents.show', row.id)" class="group flex items-center gap-3">
                    <span class="grid size-10 shrink-0 place-items-center overflow-hidden rounded-xl bg-secondary text-sm font-bold text-tertiary">
                        <img v-if="row.logo_url" :src="row.logo_url" alt="" class="size-full object-cover" />
                        <template v-else>{{ (row.agency_name ?? row.name).slice(0, 1) }}</template>
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate font-semibold text-slate-900 group-hover:text-secondary">{{ row.agency_name ?? '—' }}</span>
                        <span class="block truncate text-xs text-slate-500">{{ row.email }}</span>
                    </span>
                </Link>
            </template>
            <template #cell-name="{ row }">
                <p class="font-medium text-slate-800">{{ row.name }}</p>
                <p class="text-xs text-slate-500">{{ row.phone }}</p>
            </template>
            <template #cell-created_at="{ row }">
                <span :title="row.registered_at">{{ row.registered_ago }}</span>
            </template>
            <template #cell-status="{ row }">
                <AgentStatusBadge :status="row.status" :label="row.status_label" />
            </template>
            <template #cell-actions="{ row }">
                <div class="flex items-center justify-end gap-1">
                    <template v-if="row.status === 'pending' && can('agents.approve')">
                        <button type="button" class="hidden h-8 items-center gap-1 rounded-lg bg-emerald-600 px-2.5 text-xs font-semibold text-white hover:bg-emerald-700 sm:inline-flex" @click="act('approve', row)"><CheckIcon class="size-4" /> Approve</button>
                    </template>
                    <RowActions :label="`Actions for ${row.agency_name ?? row.name}`">
                        <Link :href="route('admin.agents.show', row.id)" role="menuitem" :class="item"><EyeIcon class="size-4" /> View details</Link>
                        <a v-if="row.whatsapp" :href="wa(row.whatsapp)" target="_blank" rel="noopener" role="menuitem" :class="item"><ChatBubbleLeftRightIcon class="size-4" /> WhatsApp</a>
                        <template v-if="can('agents.approve')">
                            <button v-if="['pending', 'rejected'].includes(row.status)" type="button" role="menuitem" :class="item" @click="act('approve', row)"><CheckIcon class="size-4 text-emerald-600" /> Approve</button>
                            <button v-if="row.status === 'pending'" type="button" role="menuitem" :class="[item, '!text-rose-600']" @click="act('reject', row)"><XMarkIcon class="size-4" /> Reject</button>
                        </template>
                        <template v-if="can('agents.suspend')">
                            <button v-if="row.status === 'approved'" type="button" role="menuitem" :class="[item, '!text-rose-600']" @click="act('suspend', row)"><NoSymbolIcon class="size-4" /> Suspend</button>
                            <button v-if="row.status === 'suspended'" type="button" role="menuitem" :class="item" @click="act('reactivate', row)"><ArrowPathIcon class="size-4" /> Reactivate</button>
                        </template>
                    </RowActions>
                </div>
            </template>
        </DataTable>

        <AgentActionDialog :action="action" @close="action = null" />
    </AdminLayout>
</template>
