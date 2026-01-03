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
                                    Dirty-Craft
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
</script>

<style scoped>
.about-page {
    min-height: 100vh;
}

.about-hero {
    background: linear-gradient(135deg, #0d4a52 0%, #0a3d44 50%, #0d4a3f 100%);
    padding: calc(var(--spacing-3xl) + 60px) var(--spacing-md)
        var(--spacing-3xl);
    text-align: center;
    color: #fff;
    position: relative;
    overflow: hidden;
}

.about-content {
    max-width: 800px;
    margin: 0 auto;
}

.about-title {
    font-size: clamp(2.5rem, 5vw, 4rem);
    font-weight: 700;
    margin-bottom: var(--spacing-lg);
    letter-spacing: -0.02em;
}

.about-subtitle {
    font-size: clamp(1.125rem, 2vw, 1.5rem);
    color: rgb(255 255 255 / 90%);
    line-height: 1.6;
}

.about-section {
    padding: var(--spacing-3xl) 0;
}

.about-section:nth-child(even) {
    background: var(--color-background-light);
}

.about-text {
    max-width: 800px;
    margin: 0 auto;
}

.section-title {
    font-size: clamp(1.75rem, 3vw, 2.5rem);
    font-weight: 700;
    margin-bottom: var(--spacing-lg);
    color: var(--color-text-primary);
}

.about-text p {
    font-size: 1.125rem;
    line-height: 1.8;
    color: var(--color-text-secondary);
    margin-bottom: var(--spacing-lg);
}

.about-text p:last-child {
    margin-bottom: 0;
}

.link {
    color: var(--color-primary);
    text-decoration: none;
    font-weight: 500;
    transition: color var(--transition-base);
}

.link:hover {
    color: var(--color-primary-dark);
    text-decoration: underline;
}

@media (width <= 768px) {
    .about-hero {
        padding: calc(var(--spacing-2xl) + 60px) var(--spacing-md)
            var(--spacing-2xl);
    }

    .about-section {
        padding: var(--spacing-2xl) 0;
    }
}
</style>
