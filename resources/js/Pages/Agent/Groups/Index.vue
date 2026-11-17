<script setup>
import { Link } from '@inertiajs/vue3';
import AgentLayout from '@/Layouts/AgentLayout.vue';
import CategoryCard from '@/Components/CategoryCard.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { formatPKR } from '@/Composables/useMoney';

defineProps({ categories: { type: Array, required: true } });
</script>

<template>
    <AgentLayout title="All Groups">
        <div class="mb-8 flex flex-col items-center text-center">
            <p class="text-xs font-semibold uppercase tracking-[0.25em] text-tertiary-600">Choose a destination</p>
            <h2 class="mt-2 font-display text-2xl font-semibold sm:text-3xl">Group Fares</h2>
            <div class="gold-rule mt-4" />
        </div>

        <div v-if="categories.length" class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
            <Link
                v-for="c in categories" :key="c.id" :href="route('agent.groups.show', c.slug)"
                class="rounded-2xl focus:outline-none focus-visible:ring-4 focus-visible:ring-tertiary-300"
                :aria-label="`${c.name}: ${c.flights_count} groups available`"
            >
                <CategoryCard :category="c" :muted="!c.flights_count">
                    <div class="mt-4 flex items-center justify-between border-t border-dashed border-slate-200 pt-4 text-sm">
                        <span :class="c.flights_count ? 'font-semibold text-emerald-700' : 'text-slate-400'">
                            {{ c.flights_count ? `${c.flights_count} ${c.flights_count === 1 ? 'group' : 'groups'} available` : 'No groups right now' }}
                        </span>
                        <span v-if="c.from_price" class="text-xs text-slate-500">from <strong class="font-display text-secondary">{{ formatPKR(c.from_price) }}</strong></span>
                    </div>
                </CategoryCard>
            </Link>
        </div>
        <div v-else class="card">
            <EmptyState title="No groups published yet" description="New group fares will appear here as soon as they are live." />
        </div>
    </AgentLayout>
</template>
