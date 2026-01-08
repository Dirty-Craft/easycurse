<template>
    <Head :title="t('ads.title')" />
    <AppLayout>
        <div class="ads-page">
            <section class="ads-hero">
                <div class="container">
                    <div class="ads-content">
                        <h1 class="ads-title">{{ t("ads.hero.title") }}</h1>
                        <p class="ads-subtitle">
                            {{ t("ads.hero.subtitle") }}
                        </p>
                    </div>
                </div>
            </section>

            <section class="ads-section">
                <div class="container">
                    <div class="ads-text">
                        <h2 class="section-title">
                            {{ t("ads.how.title") }}
                        </h2>
                        <p>
                            {{ t("ads.how.p1") }}
                        </p>
                        <p>
                            {{ t("ads.how.p2") }}
                        </p>
                        <p>
                            <template
                                v-for="(part, index) in contactParts"
                                :key="index"
                            >
                                {{ part }}
                                <a
                                    v-if="index < contactParts.length - 1"
                                    href="mailto:ads@easycurse.com"
                                    class="link"
                                >
                                    ads@easycurse.com
                                </a>
                            </template>
                        </p>
                    </div>
                </div>
            </section>

            <section class="ads-section">
                <div class="container">
                    <div class="ads-text">
                        <h2 class="section-title">
                            {{ t("ads.benefits.title") }}
                        </h2>
                        <p>
                            {{ t("ads.benefits.p1") }}
                        </p>
                        <p>
                            {{ t("ads.benefits.p2") }}
                        </p>
                    </div>
                </div>
            </section>

            <section class="ads-section">
                <div class="container">
                    <div class="ads-text">
                        <h2 class="section-title">
                            {{ t("ads.contact.title") }}
                        </h2>
                        <p>
                            {{ t("ads.contact.p1") }}
                        </p>
                        <p>
                            <a href="mailto:ads@easycurse.com" class="link">
                                ads@easycurse.com
                            </a>
                        </p>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

<script setup>
import { Head } from "@inertiajs/vue3";
import { computed } from "vue";
import AppLayout from "../Layouts/AppLayout.vue";
import { useTranslations } from "../composables/useTranslations";

const { t } = useTranslations();

// Helper function to split text with :link placeholder and return parts
const splitWithLink = (text) => {
    return text.split(":link");
};

// Computed property for contact paragraph with link
const contactParts = computed(() => splitWithLink(t("ads.how.p3")));
</script>
