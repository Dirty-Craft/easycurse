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
                <span class="advertisement-icon">✨</span>
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
import { useTranslations } from "../composables/useTranslations";

const { t, translations } = useTranslations();

// You can customize these props or make them configurable
const adText = computed(() => {
    // Try to get from translations, fallback to default
    return t("advertisement.text") || "Check out our amazing products!";
});

const adUrl = computed(() => {
    // Try to get from translations, fallback to default
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
