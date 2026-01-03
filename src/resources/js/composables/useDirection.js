import { usePage } from "@inertiajs/vue3";
import { watch, onMounted } from "vue";

export function useDirection() {
    const page = usePage();

    const applyDirection = () => {
        // translations might be a function, so call it if needed
        const translations = page.props.translations || {};
        const trans =
            typeof translations === "function" ? translations() : translations;

        const direction = trans["direction"] || "LTR";
        const dir = direction.toLowerCase() === "rtl" ? "rtl" : "ltr";

        document.documentElement.setAttribute("dir", dir);
    };

    // Apply direction on mount
    onMounted(() => {
        applyDirection();
    });

    // Watch for locale changes (when translations update)
    watch(
        () => page.props.translations,
        () => {
            applyDirection();
        },
        { deep: true, immediate: true },
    );

    return { applyDirection };
}
