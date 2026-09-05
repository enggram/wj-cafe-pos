import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import AppLayout from './Layouts/AppLayout.vue';
import '../css/app.css';

createInertiaApp({
    title: (title) => title ? `${title} - WhiteJersey Cafe POS` : 'WhiteJersey Cafe POS',
    resolve: (name) => {
        const page = resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        );
        page.then((module) => {
            module.default.layout = module.default.layout || AppLayout;
        });
        return page;
    },
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#dc2626',
    },
});
