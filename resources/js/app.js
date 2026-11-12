import '../css/app.css';
import '@fontsource-variable/geist-mono';
import '../css/nova.css';
import '../css/nova-dark-compat.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { installGlobalPress, installClickBurst, vReveal, vLift, vPress } from './motion';

// Efek tekan bergaya spring + riak klik untuk semua tombol/tautan (Framer Motion).
installGlobalPress();
installClickBurst();

const appName = import.meta.env.VITE_APP_NAME || 'SI SINDEN - DENINTEL KODAERAL V';

createInertiaApp({
    title: (title) => title ? `${title} - ${appName}` : appName,
    resolve: (name) => resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .directive('reveal', vReveal)
            .directive('lift', vLift)
            .directive('press', vPress)
            .mount(el);
    },
    progress: {
        color: '#2563EB',
        showSpinner: false,
    },
});
