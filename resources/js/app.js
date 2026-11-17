import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { applyTheme } from './theme';
import { vCan } from './Composables/usePermissions';

const appName = import.meta.env.VITE_APP_NAME || 'Shaheen Group of Travels';

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        applyTheme(props.initialPage.props.settings?.theme);

        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .directive('can', vCan)
            .mount(el);
    },
    progress: {
        color: '#D4A84B',
    },
});

// After a failed form submit, bring the first invalid field into view and focus it.
router.on('error', () => {
    requestAnimationFrame(() => {
        const field = document.querySelector('[aria-invalid="true"], .border-rose-300');
        if (field) {
            field.scrollIntoView({ behavior: 'smooth', block: 'center' });
            field.focus?.({ preventScroll: true });
        }
    });
});

// Keep colors in sync after an admin saves new theme settings.
router.on('navigate', (event) => applyTheme(event.detail.page.props.settings?.theme));
