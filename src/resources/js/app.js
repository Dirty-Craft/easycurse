import "./bootstrap";
import "../css/app.css";

import { createApp, h } from "vue";
import { createInertiaApp } from "@inertiajs/vue3";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";

const appName = import.meta.env.VITE_APP_NAME || "EasyCurse";

// Initialize theme before app loads to prevent flash
const initTheme = () => {
    const THEME_STORAGE_KEY = "easycurse-theme";
    const savedTheme = localStorage.getItem(THEME_STORAGE_KEY);

    if (savedTheme && (savedTheme === "dark" || savedTheme === "light")) {
        document.documentElement.setAttribute("data-theme", savedTheme);
    } else {
        // Check system preference
        const prefersLight = window.matchMedia(
            "(prefers-color-scheme: light)",
        ).matches;
        const theme = prefersLight ? "light" : "dark";
        document.documentElement.setAttribute("data-theme", theme);
        localStorage.setItem(THEME_STORAGE_KEY, theme);
    }
};

// Initialize theme immediately
initTheme();

createInertiaApp({
    title: (title) => (title ? `${title} | ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob("./Pages/**/*.vue"),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: "#4B5563",
    },
});
