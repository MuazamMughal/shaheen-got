<script setup>
defineProps({ logs: { type: Array, default: () => [] } });
const dot = { pending: 'bg-amber-500', payment_approved: 'bg-sky-500', confirmed: 'bg-emerald-500', rejected: 'bg-rose-500', cancelled: 'bg-slate-400' };
</script>

<template>
    <section class="card p-5">
        <h3 class="font-semibold">History</h3>
        <ol class="mt-4 space-y-4">
            <li v-for="log in logs" :key="log.id" class="relative flex gap-3 pl-1">
                <span :class="['mt-1.5 size-2.5 shrink-0 rounded-full ring-4 ring-white', dot[log.to_status] ?? 'bg-slate-400']" />
                <div class="min-w-0">
                    <p class="text-sm font-medium text-slate-800">{{ log.note || log.to_label }}</p>
                    <p class="text-xs text-slate-500">{{ log.at }}<template v-if="log.by"> · {{ log.by }}</template></p>
                </div>
            </li>
        </ol>
    </section>
</template>
