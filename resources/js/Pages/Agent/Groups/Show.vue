<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import AgentLayout from '@/Layouts/AgentLayout.vue';
import AgentFlightTable from '@/Components/flights/AgentFlightTable.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Button from '@/Components/ui/Button.vue';

const props = defineProps({
    category: { type: Object, required: true },
    sectors: { type: Array, required: true },
    flights: { type: Array, required: true },
});

// Sector buttons filter on the page (no reload). Remembered in the URL hash.
const active = ref(Number(window.location.hash.replace('#sector-', '')) || null);
function choose(id) {
    active.value = id;
    history.replaceState(null, '', id ? `#sector-${id}` : window.location.pathname);
}

const groups = computed(() =>
    props.sectors
        .filter((s) => !active.value || s.id === active.value)
        .map((s) => ({ sector: s, flights: props.flights.filter((f) => f.sector_id === s.id) }))
        .filter((g) => g.flights.length),
);
</script>

<template>
    <AgentLayout :title="category.name">
        <!-- Hero -->
        <div class="relative mb-6 overflow-hidden rounded-2xl bg-secondary">
            <img v-if="category.image_url" :src="category.image_url" alt="" class="absolute inset-0 size-full object-cover opacity-35" />
            <div class="absolute inset-0 bg-gradient-to-r from-secondary via-secondary/80 to-transparent" />
            <div class="relative flex flex-col gap-4 p-6 sm:flex-row sm:items-end sm:justify-between sm:p-8">
                <div>
                    <Link :href="route('agent.groups.index')" class="inline-flex items-center gap-1 text-xs font-semibold text-tertiary hover:text-white"><ArrowLeftIcon class="size-3.5" /> All groups</Link>
                    <h2 class="mt-2 font-display text-2xl font-semibold !text-white sm:text-3xl">{{ category.name }}</h2>
                    <p v-if="category.country" class="mt-1 flex items-center gap-2 text-sm text-white/75">
                        <img v-if="category.flag_url" :src="category.flag_url" alt="" class="size-5 rounded-full object-cover" /> {{ category.country }}
                    </p>
                    <p v-if="category.description" class="mt-3 max-w-xl text-sm text-white/70">{{ category.description }}</p>
                </div>
                <p class="text-sm text-white/80"><strong class="font-display text-2xl text-tertiary">{{ flights.length }}</strong> groups available</p>
            </div>
        </div>

        <!-- Sector buttons -->
        <div v-if="sectors.length" class="mb-6 flex flex-wrap justify-center gap-2.5" role="tablist" aria-label="Sectors">
            <button
                type="button" role="tab" :aria-selected="!active" @click="choose(null)"
                :class="['rounded-full px-6 py-2.5 text-sm font-semibold transition', !active ? 'bg-secondary text-tertiary shadow-md shadow-secondary/25' : 'bg-white text-slate-700 shadow-sm ring-1 ring-slate-200 hover:ring-slate-300']"
            >All</button>
            <button
                v-for="s in sectors" :key="s.id" type="button" role="tab" :aria-selected="active === s.id" @click="choose(s.id)"
                :class="['rounded-full px-5 py-2.5 text-sm font-semibold tracking-wide transition', active === s.id ? 'bg-secondary text-tertiary shadow-md shadow-secondary/25' : 'bg-white text-slate-700 shadow-sm ring-1 ring-slate-200 hover:ring-slate-300']"
            >{{ s.name }}</button>
        </div>

        <div v-if="groups.length" class="space-y-6">
            <AgentFlightTable v-for="g in groups" :id="`sector-${g.sector.id}`" :key="g.sector.id" :sector="g.sector" :flights="g.flights" @book="(f) => router.visit(route('agent.bookings.create', f.id))" />
        </div>
        <div v-else class="card">
            <EmptyState title="No groups available right now" description="Seats in this category are sold out or not published yet. Please check back soon.">
                <Button variant="secondary" :href="route('agent.groups.index')">Browse other groups</Button>
            </EmptyState>
        </div>

    </AgentLayout>
</template>
