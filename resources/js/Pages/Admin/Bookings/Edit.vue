<script setup>
import { computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import BookingForm from '@/Components/bookings/BookingForm.vue';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({
    flight: { type: Object, required: true },
    booking: { type: Object, required: true },
    paxRules: { type: Object, required: true },
    countsLocked: { type: Boolean, default: false },
});

const { canViewPrices } = usePermissions();
// The booking's own snapshot prices drive the breakdown.
const flightWithPrices = computed(() => ({ ...props.flight }));
</script>

<template>
    <AdminLayout :title="`Edit ${booking.booking_no}`" :subtitle="booking.agent?.agency_name">
        <div class="mx-auto max-w-6xl">
            <BookingForm
                :flight="flightWithPrices" :booking="booking" :pax-rules="paxRules"
                :submit-url="route('admin.bookings.update', booking.id)" method="put"
                :back-url="route('admin.bookings.show', booking.id)"
                :counts-locked="countsLocked" :show-prices="canViewPrices()" submit-label="Save changes"
            />
        </div>
    </AdminLayout>
</template>
