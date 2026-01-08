<template>
    <Head :title="t('about.title')" />
    <AppLayout>
        <div class="about-page">
            <section class="about-hero">
                <div class="container">
                    <div class="about-content">
                        <h1 class="about-title">{{ t("about.hero.title") }}</h1>
                        <p class="about-subtitle">
                            {{ t("about.hero.subtitle") }}
                        </p>
                    </div>
                </div>
            </section>

            <section class="about-section">
                <div class="container">
                    <div class="about-text">
                        <h2 class="section-title">
                            {{ t("about.mission.title") }}
                        </h2>
                        <p>
                            {{ t("about.mission.p1") }}
                        </p>
                        <p>
                            {{ t("about.mission.p2") }}
                        </p>
                    </div>
                </div>
            </section>

            <section class="about-section">
                <div class="container">
                    <div class="about-text">
                        <h2 class="section-title">
                            {{ t("about.what.title") }}
                        </h2>
                        <p>
                            {{ t("about.what.p1") }}
                        </p>
                        <p>
                            {{ t("about.what.p2") }}
                        </p>
                    </div>
                </div>
            </section>

            <section class="about-section">
                <div class="container">
                    <div class="about-text">
                        <h2 class="section-title">
                            {{ t("about.opensource.title") }}
                        </h2>
                        <p>
                            <template
                                v-for="(part, index) in opensourceP1Parts"
                                :key="index"
                            >
                                {{ part }}
                                <a
                                    v-if="index < opensourceP1Parts.length - 1"
                                    href="https://github.com/Dirty-Craft"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="link"
                                >
                                    Dirty Craft
                                </a>
                            </template>
                        </p>
                        <p>
                            <template
                                v-for="(part, index) in opensourceP2Parts"
                                :key="index"
                            >
                                {{ part }}
                                <a
                                    v-if="index < opensourceP2Parts.length - 1"
                                    href="https://github.com/Dirty-Craft/easycurse"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="link"
                                >
                                    GitHub
                                </a>
                            </template>
                        </p>
                        <p>
                            <template
                                v-for="(part, index) in opensourceP3Parts"
                                :key="index"
                            >
                                {{ part }}
                                <a
                                    v-if="index < opensourceP3Parts.length - 1"
                                    href="https://opensource.org/licenses/MIT"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="link"
                                >
                                    MIT License
                                </a>
                            </template>
                        </p>
                    </div>
                </div>
            </section>

            <section class="about-section">
                <div class="container">
                    <div class="about-text">
                        <h2 class="section-title">
                            {{ t("about.support.title") }}
                        </h2>
                        <p>
                            {{ t("about.support.p1") }}
                        </p>
                        <p>
                            <template
                                v-for="(part, index) in supportParts"
                                :key="index"
                            >
                                {{ part }}
                                <Link
                                    v-if="index < supportParts.length - 1"
                                    href="/donate"
                                    class="link"
                                >
                                    {{ t("about.support.link_text") }}
                                </Link>
                            </template>
                        </p>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

<script setup>
import { Head, Link } from "@inertiajs/vue3";
import { computed } from "vue";
import AppLayout from "../Layouts/AppLayout.vue";
import { useTranslations } from "../composables/useTranslations";

const { t } = useTranslations();

// Helper function to split text with :link placeholder and return parts
const splitWithLink = (text) => {
    return text.split(":link");
};

// Computed properties for paragraphs with links
const opensourceP1Parts = computed(() =>
    splitWithLink(t("about.opensource.p1")),
);
const opensourceP2Parts = computed(() =>
    splitWithLink(t("about.opensource.p2")),
);
const opensourceP3Parts = computed(() =>
    splitWithLink(t("about.opensource.p3")),
);
const supportParts = computed(() => splitWithLink(t("about.support.p2")));
</script>
