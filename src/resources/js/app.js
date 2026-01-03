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

// Initialize font based on locale
const initFont = (translations = {}) => {
    // translations might be a function, so call it if needed
    const trans =
        typeof translations === "function" ? translations() : translations;
    const fontFamily =
        trans["font.family"] ||
        "'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif";
    const fontFamilyHeading = trans["font.family_heading"] || fontFamily;

    document.documentElement.style.setProperty(
        "--font-family-base",
        fontFamily,
    );
    document.documentElement.style.setProperty(
        "--font-family-heading",
        fontFamilyHeading,
    );
};

// Initialize direction based on locale
const initDirection = (translations = {}) => {
    // translations might be a function, so call it if needed
    const trans =
        typeof translations === "function" ? translations() : translations;
    const direction = trans["direction"] || "LTR";
    const dir = direction.toLowerCase() === "rtl" ? "rtl" : "ltr";

    document.documentElement.setAttribute("dir", dir);
};

createInertiaApp({
    title: (title) => (title ? `${appName} | ${title}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob("./Pages/**/*.vue"),
        ),
    setup({ el, App, props, plugin }) {
        // Apply font and direction from translations after Inertia loads
        const translations = props.initialPage?.props?.translations;
        if (translations) {
            initFont(translations);
            initDirection(translations);
        }

        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: "#4B5563",
    },
});
