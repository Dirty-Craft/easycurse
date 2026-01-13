<template>
    <Head :title="t('premium.title')" />
    <AppLayout>
        <div class="about-page">
            <section class="about-hero">
                <div class="container">
                    <div class="about-content">
                        <h1 class="about-title">
                            {{ t("premium.hero.title") }}
                        </h1>
                        <p class="about-subtitle">
                            {{ t("premium.hero.subtitle") }}
                        </p>
                    </div>
                </div>
            </section>

            <section class="about-section">
                <div class="container">
                    <div class="about-text">
                        <h2 class="section-title">
                            {{ t("premium.comparison.title") }}
                        </h2>
                        <div class="comparison-grid">
                            <div class="comparison-card">
                                <div class="comparison-header">
                                    <h3 class="comparison-title">
                                        {{ t("premium.free.title") }}
                                    </h3>
                                </div>
                                <ul class="comparison-list">
                                    <li class="comparison-item">
                                        <span class="comparison-icon">✓</span>
                                        <span>{{
                                            t("premium.free.runs")
                                        }}</span>
                                    </li>
                                </ul>
                            </div>
                            <div
                                class="comparison-card comparison-card-featured"
                            >
                                <div class="comparison-header">
                                    <h3 class="comparison-title">
                                        {{ t("premium.premium.title") }}
                                    </h3>
                                </div>
                                <ul class="comparison-list">
                                    <li class="comparison-item">
                                        <span class="comparison-icon">✓</span>
                                        <span>{{
                                            t("premium.premium.runs")
                                        }}</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="about-section">
                <div class="container">
                    <div class="about-text">
                        <h2 class="section-title">
                            {{ t("premium.upgrade.title") }}
                        </h2>
                        <p>
                            {{ t("premium.upgrade.p1") }}
                        </p>
                        <p>
                            <a href="mailto:upgrade@easycurse.com" class="link">
                                upgrade@easycurse.com
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
import AppLayout from "../Layouts/AppLayout.vue";
import { useTranslations } from "../composables/useTranslations";

const { t } = useTranslations();
</script>
