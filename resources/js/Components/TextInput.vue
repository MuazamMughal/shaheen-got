<script setup>
import { onMounted, ref } from 'vue';

const model = defineModel({
    type: String,
    required: true,
});

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        class="h-11 rounded-xl border-slate-200 text-sm text-slate-900 shadow-none focus:border-secondary-400 focus:ring-4 focus:ring-secondary-100"
        v-model="model"
        ref="input"
    />
</template>
