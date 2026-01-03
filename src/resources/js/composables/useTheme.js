import { ref } from "vue";

const THEME_STORAGE_KEY = "easycurse-theme";
const themes = ["dark", "light"];

// Get initial theme from DOM attribute (set by app.js), localStorage, or system preference
const getInitialTheme = () => {
    if (typeof window === "undefined") return "dark";

    // First check if theme is already set on document (by app.js)
    const docTheme = document.documentElement.getAttribute("data-theme");
    if (docTheme && themes.includes(docTheme)) {
        return docTheme;
    }

    // Then check localStorage
    const savedTheme = localStorage.getItem(THEME_STORAGE_KEY);
    if (savedTheme && themes.includes(savedTheme)) {
        return savedTheme;
    }

    // Finally, check system preference
    const prefersLight = window.matchMedia(
        "(prefers-color-scheme: light)",
    ).matches;
    return prefersLight ? "light" : "dark";
};

const currentTheme = ref(getInitialTheme());

export function useTheme() {
    const setTheme = (theme) => {
        if (!themes.includes(theme)) {
            console.warn(`Invalid theme: ${theme}. Using 'dark' instead.`);
            theme = "dark";
        }

        currentTheme.value = theme;
        if (typeof document !== "undefined") {
            document.documentElement.setAttribute("data-theme", theme);
        }
        if (typeof localStorage !== "undefined") {
            localStorage.setItem(THEME_STORAGE_KEY, theme);
        }
    };

    const toggleTheme = () => {
        const newTheme = currentTheme.value === "dark" ? "light" : "dark";
        setTheme(newTheme);
    };

    const initTheme = () => {
        // Check localStorage first
        if (typeof window === "undefined") return;

        const savedTheme = localStorage.getItem(THEME_STORAGE_KEY);

        // If no saved theme, check system preference
        if (!savedTheme) {
            const prefersLight = window.matchMedia(
                "(prefers-color-scheme: light)",
            ).matches;
            setTheme(prefersLight ? "light" : "dark");
        } else {
            setTheme(savedTheme);
        }
    };

    // Watch for system theme changes
    if (typeof window !== "undefined") {
        const mediaQuery = window.matchMedia("(prefers-color-scheme: light)");
        const handleChange = (e) => {
            // Only auto-switch if user hasn't manually set a preference
            if (!localStorage.getItem(THEME_STORAGE_KEY)) {
                setTheme(e.matches ? "light" : "dark");
            }
        };

        // Use addEventListener if available, fallback to addListener for older browsers
        if (mediaQuery.addEventListener) {
            mediaQuery.addEventListener("change", handleChange);
        } else if (mediaQuery.addListener) {
            mediaQuery.addListener(handleChange);
        }
    }

    return {
        currentTheme,
        setTheme,
        toggleTheme,
        initTheme,
    };
}
