import { nextTick, watch } from 'vue';

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
 *   const submit = () => validate() && form.post(..., { onError: focusFirstError });
 *
 * A failed validate() moves focus to (and scrolls to) the first invalid field.
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

    // Whole number within [min, max] (either bound optional).
    integer: ({ min, max } = {}, msg) => (v) => {
        if (isEmpty(v)) return null;
        const s = String(v).trim();
        if (!/^-?\d+$/.test(s)) return msg ?? 'Enter a whole number.';
        const n = Number(s);
        if (min !== undefined && n < min) return msg ?? `Must be at least ${min}.`;
        if (max !== undefined && n > max) return msg ?? `Must not be more than ${max}.`;
        return null;
    },

    // ISO dates (YYYY-MM-DD) compare correctly as strings.
    dateAfterOrEqual: (other, msg) => (v) => {
        const o = typeof other === 'function' ? other() : other;
        return isEmpty(v) || isEmpty(o) || String(v) >= String(o) ? null : msg ?? 'Date is too early.';
    },

    // Every item of an array field must pass `rule`; reports the first failing item.
    each: (rule, msg) => (list) => {
        for (const item of list ?? []) {
            const err = rule(item);
            if (err) return msg ? msg(item) : err;
        }
        return null;
    },

    maxItems: (n, msg) => (list) => ((list ?? []).length <= n ? null : msg ?? `You can add up to ${n}.`),
};

export const today = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

// Focus and scroll to the first visible invalid control (client or server errors).
export function focusFirstError(root = document) {
    nextTick(() => {
        const el = [...root.querySelectorAll('[aria-invalid="true"]')].find((e) => e.offsetParent !== null);
        if (!el) return;
        el.scrollIntoView({ block: 'center', behavior: 'smooth' });
        el.focus({ preventScroll: true });
    });
}

export function useValidation(form, schema, { focus = true } = {}) {
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
        if (!ok && focus) focusFirstError();
        return ok;
    };

    // Once a field has been checked, re-check it live as the user corrects it.
    // All touched fields are re-checked so cross-field rules (password confirmation) stay in sync.
    watch(
        () => Object.keys(schema).map((f) => form[f]),
        () => touched.forEach((f) => validateField(f)),
        { deep: true }, // array fields (email lists) change in place
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
