import { usePage } from '@inertiajs/vue3';

/**
 * Permission helpers for templates. The server is always the real gatekeeper;
 * these only hide UI the user can't use.
 *
 *   const { can, canAny } = usePermissions();
 *   <Button v-if="can('flights.create')">…</Button>
 */
export function usePermissions() {
    const page = usePage();

    const list = () => page.props.auth?.permissions ?? [];

    const can = (permission) => !permission || list().includes(permission);
    const canAny = (permissions) => permissions.some((p) => can(p));
    const canViewPrices = () => !!page.props.auth?.can_view_prices;

    return { can, canAny, canViewPrices };
}

/**
 * v-can="'flights.create'" or v-can="['a', 'b']" (any). Hides the element.
 * Prefer v-if="can(...)" for anything sizeable; this is for small buttons.
 */
export const vCan = {
    mounted: apply,
    updated: apply,
};

function apply(el, binding) {
    const { can, canAny } = usePermissions();
    const allowed = Array.isArray(binding.value) ? canAny(binding.value) : can(binding.value);
    el.style.display = allowed ? '' : 'none';
}
