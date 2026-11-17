import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * Shared behaviour for master-data list pages: modal form state, delete
 * confirmation, active toggle and up/down reordering.
 *
 *   const list = useMasterList({ routePrefix: 'admin.categories', items: () => props.categories });
 */
export function useMasterList({ routePrefix, items }) {
    const rows = ref([...items()]);
    watch(items, (v) => (rows.value = [...v]));

    const editing = ref(null);   // record being edited, or {} for "new"
    const deleting = ref(null);
    const busy = ref(false);

    const openCreate = () => (editing.value = {});
    const openEdit = (row) => (editing.value = row);
    const closeForm = () => (editing.value = null);

    function toggle(row) {
        router.patch(route(`${routePrefix}.toggle`, row.id), {}, { preserveScroll: true });
    }

    function destroy() {
        busy.value = true;
        router.delete(route(`${routePrefix}.destroy`, deleting.value.id), {
            preserveScroll: true,
            onFinish: () => { busy.value = false; deleting.value = null; },
        });
    }

    /** Move within `subset` (defaults to all rows) and save the new order. */
    function move(row, delta, subset = rows.value) {
        const list = [...subset];
        const i = list.findIndex((r) => r.id === row.id);
        const j = i + delta;
        if (i < 0 || j < 0 || j >= list.length) return;
        [list[i], list[j]] = [list[j], list[i]];
        router.post(route(`${routePrefix}.reorder`), { ids: list.map((r) => r.id) }, { preserveScroll: true });
    }

    return { rows, editing, deleting, busy, openCreate, openEdit, closeForm, toggle, destroy, move };
}
