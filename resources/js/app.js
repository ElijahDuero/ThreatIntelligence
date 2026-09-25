import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { registerSW } from 'virtual:pwa-register';
import CyberLayout from './Layouts/CyberLayout.vue';
import { initKineticScroll } from './Utils/kineticScroll';

if (typeof window !== 'undefined' && 'serviceWorker' in navigator) {
    registerSW({ immediate: true });
}

// Universal Kinetic Momentum Scrolling Engine
const kineticEngine = initKineticScroll();
if (kineticEngine && typeof window !== 'undefined') {
    window.__kineticScroll = kineticEngine;
    router.on('start', () => kineticEngine.reset());
    router.on('finish', () => kineticEngine.reset());
}

createInertiaApp({
    title: (title) => (title ? `${title} | DarkWeb` : 'DarkWeb'),
    resolve: async (name) => {
        const page = await resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue'));
        if (page.default.layout === undefined && !name.startsWith('Auth/')) {
            page.default.layout = CyberLayout;
        }
        return page;
    },
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#10b981',
        showSpinner: true,
    },
});
