import { usePage } from "@inertiajs/vue3";

export function useTranslations() {
    const page = usePage();
    const translations = page.props.translations || {};

    const t = (key, params = {}) => {
        let translation = translations[key] || key;

        // Replace parameters in the translation
        Object.keys(params).forEach((param) => {
            translation = translation.replace(
                new RegExp(`:${param}`, "g"),
                params[param],
            );
        });

        return translation;
    };

    return { t, translations };
}
