import { watch } from 'vue';

/*
 * Client-side validation for Inertia `useForm` forms.
 *
 * Errors are written into `form.errors` (via setError), so they render through the
 * same `:error` props the server errors use. The server still validates everything;
 * these rules mirror it so people get feedback before a round trip.
 *
 *   const { validate } = useValidation(form, {
 *       email: [rules.required('Enter your email.'), rules.email()],
 *   });
 *   const submit = () => validate() && form.post(...);
 */

const isEmpty = (v) => v === null || v === undefined || (typeof v === 'string' && v.trim() === '');

export const rules = {
    required: (msg = 'This field is required.') => (v) => (isEmpty(v) ? msg : null),

    email: (msg = 'Enter a valid email address.') => (v) =>
        isEmpty(v) || /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(String(v).trim()) ? null : msg,

    min: (n, msg) => (v) =>
        isEmpty(v) || String(v).length >= n ? null : msg ?? `Must be at least ${n} characters.`,

    max: (n, msg) => (v) =>
        isEmpty(v) || String(v).trim().length <= n ? null : msg ?? `Must not be more than ${n} characters.`,

    pattern: (re, msg) => (v) => (isEmpty(v) || re.test(String(v).trim()) ? null : msg),

    // Digit count after stripping spaces, dashes, + etc.
    digits: (min, max, msg) => (v) => {
        if (isEmpty(v)) return null;
        const n = String(v).replace(/\D/g, '').length;
        return n >= min && n <= max ? null : msg ?? `Must be ${min}–${max} digits.`;
    },

    // Compare with another field of the same form: rules.same(() => form.password, '…')
    same: (other, msg = 'Values do not match.') => (v) => (isEmpty(v) || v === other() ? null : msg),

    accepted: (msg = 'This must be accepted.') => (v) => (v === true ? null : msg),
};

export function useValidation(form, schema) {
    const touched = new Set();

    const check = (field) => {
        for (const rule of schema[field] ?? []) {
            const msg = rule(form[field]);
            if (msg) return msg;
        }
        return null;
    };

    const validateField = (field) => {
        const msg = check(field);
        if (msg) form.setError(field, msg);
        else form.clearErrors(field);
        return !msg;
    };

    // Validate the given fields (default: all). Returns true when they all pass.
    const validate = (fields = Object.keys(schema)) => {
        let ok = true;
        for (const f of fields) {
            touched.add(f);
            if (!validateField(f)) ok = false;
        }
        return ok;
    };

    // Once a field has been checked, re-check it live as the user corrects it.
    // All touched fields are re-checked so cross-field rules (password confirmation) stay in sync.
    watch(
        () => Object.keys(schema).map((f) => form[f]),
        () => touched.forEach((f) => validateField(f)),
    );

    // On blur: start validating a field once the user has typed something into it,
    // so tabbing past an empty field doesn't flash an error.
    const touch = (field) => {
        if (!touched.has(field) && isEmpty(form[field])) return;
        touched.add(field);
        validateField(field);
    };

    const reset = () => touched.clear();

    return { validate, validateField, touch, reset };
}
