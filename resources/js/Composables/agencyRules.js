import { rules as r } from '@/Composables/useValidation';

/*
 * Agency field rules shared by registration, the agent's own agency form and the
 * admin agent edit. Mirrors the server (RegisteredUserController, Agent\AgencyController,
 * Admin\AgentController) so the three forms always agree.
 */
export const PHONE = /^[0-9+\-\s()]{7,30}$/;

export const agencyRules = {
    agency_name: [r.required('Enter the agency name.'), r.max(160)],
    city: [r.required('Enter the city.'), r.max(80)],
    address: [r.max(500)],
    phone: [r.required('Enter a phone number.'), r.pattern(PHONE, 'Enter a valid phone number.')],
    whatsapp: [r.digits(10, 15, 'Enter a WhatsApp number with country code (10–15 digits).')],
};
