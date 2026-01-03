import { usePage } from "@inertiajs/vue3";
import { watch, onMounted } from "vue";

export function useFont() {
    const page = usePage();

    const applyFont = () => {
        // translations might be a function, so call it if needed
        const translations = page.props.translations || {};
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

    // Apply font on mount
    onMounted(() => {
        applyFont();
    });

    // Watch for locale changes (when translations update)
    watch(
        () => page.props.translations,
        () => {
            applyFont();
        },
        { deep: true, immediate: true },
    );

    return { applyFont };
}
