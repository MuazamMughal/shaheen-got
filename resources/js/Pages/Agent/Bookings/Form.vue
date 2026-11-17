<script setup>
import AgentLayout from '@/Layouts/AgentLayout.vue';
import BookingForm from '@/Components/bookings/BookingForm.vue';

defineProps({
    flight: { type: Object, required: true },
    booking: { type: Object, default: null },
    paxRules: { type: Object, required: true },
    submitUrl: { type: String, required: true },
    method: { type: String, required: true },
    backUrl: { type: String, required: true },
});
</script>

<template>
    <AgentLayout :title="booking ? `Edit ${booking.booking_no}` : 'New booking'">
        <div class="mx-auto max-w-6xl">
            <BookingForm
                :flight="flight" :booking="booking" :pax-rules="paxRules" :submit-url="submitUrl" :method="method" :back-url="backUrl"
                :submit-label="booking ? 'Save changes' : 'Submit booking'"
            />
        </div>
    </AgentLayout>
</template>
