<template>
    <div class="advertisement">
        <a
            :href="adUrl"
            :target="isExternalLink ? '_blank' : undefined"
            :rel="isExternalLink ? 'noopener noreferrer' : undefined"
            class="advertisement-link"
        >
            <div class="advertisement-background-pattern"></div>
            <div class="advertisement-gradient-overlay"></div>
            <div class="advertisement-content">
                <span class="advertisement-text">{{ adText }}</span>
                <span class="advertisement-arrow">{{ arrow }}</span>
            </div>
            <div class="advertisement-shimmer"></div>
            <div class="advertisement-glow"></div>
        </a>
    </div>
</template>

<script setup>
import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";
import { useTranslations } from "../composables/useTranslations";

const { t, translations } = useTranslations();
const page = usePage();

// Use AD_TEXT and AD_LINK from environment variables if available, otherwise use translations
const adText = computed(() => {
    // First try environment variable
    if (page.props.adText) {
        return page.props.adText;
    }
    // Fallback to translations
    return t("advertisement.text") || "Place your advertisement here";
});

const adUrl = computed(() => {
    // First try environment variable
    if (page.props.adLink) {
        return page.props.adLink;
    }
    // Fallback to translations
    return t("advertisement.url") || "/ads";
});

const isExternalLink = computed(() => {
    const url = adUrl.value;
    return url && (url.startsWith("http://") || url.startsWith("https://"));
});

const arrow = computed(() => {
    const trans =
        typeof translations === "function" ? translations() : translations;
    const direction = trans?.["direction"] || "LTR";
    return direction.toLowerCase() === "rtl" ? "←" : "→";
});
</script>
