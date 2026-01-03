<template>
    <Head :title="t('landing.title')" />
    <AppLayout>
        <div class="landing-page">
            <!-- Hero Section -->
            <section class="hero">
                <div class="container">
                    <div class="hero-content">
                        <div class="hero-badge">
                            <span class="badge-icon">✨</span>
                            <span>{{ t("landing.hero.badge") }}</span>
                        </div>
                        <h1 class="hero-title">
                            {{ t("landing.hero.title") }}
                            <span class="gradient-text">{{
                                t("landing.hero.title_gradient")
                            }}</span>
                            {{ t("landing.hero.title_suffix") }}
                        </h1>
                        <p class="hero-subtitle">
                            {{ t("landing.hero.subtitle") }}
                        </p>
                        <div class="hero-actions">
                            <Button
                                v-if="!$page.props.auth.user"
                                tag="Link"
                                href="/register"
                                show-arrow
                            >
                                {{ t("landing.hero.get_started") }}
                            </Button>
                            <Button
                                v-else
                                tag="Link"
                                href="/mod-packs"
                                show-arrow
                            >
                                {{ t("landing.hero.my_mods") }}
                            </Button>
                            <Button
                                tag="a"
                                href="#how-it-works"
                                variant="secondary"
                            >
                                {{ t("landing.hero.learn_more") }}
                            </Button>
                        </div>
                        <div class="hero-stats">
                            <div class="stat-item">
                                <div class="stat-number emoji">♾️</div>
                                <div class="stat-label">
                                    {{ t("landing.hero.stats.unlimited") }}
                                </div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number emoji">⚡</div>
                                <div class="stat-label">
                                    {{ t("landing.hero.stats.instant") }}
                                </div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number emoji">📦</div>
                                <div class="stat-label">
                                    {{ t("landing.hero.stats.download") }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="hero-background">
                    <div class="gradient-orb orb-1"></div>
                    <div class="gradient-orb orb-2"></div>
                    <div class="gradient-orb orb-3"></div>
                    <div class="gradient-orb orb-4"></div>
                    <div class="grid-pattern"></div>
                </div>
            </section>

            <!-- Comparison Section -->
            <section class="section comparison">
                <div class="container">
                    <div class="section-header">
                        <div class="section-badge">
                            {{ t("landing.comparison.badge") }}
                        </div>
                        <h2 class="section-title">
                            {{ t("landing.comparison.title") }}
                        </h2>
                        <p class="section-subtitle">
                            {{ t("landing.comparison.subtitle") }}
                        </p>
                    </div>
                    <div class="comparison-grid">
                        <div class="comparison-card">
                            <div class="comparison-header">
                                <h3 class="comparison-title">
                                    {{
                                        t("landing.comparison.curseforge.title")
                                    }}
                                </h3>
                            </div>
                            <ul class="comparison-list">
                                <li class="comparison-item">
                                    <span class="comparison-icon">✓</span>
                                    <span>{{
                                        t("landing.comparison.curseforge.item1")
                                    }}</span>
                                </li>
                                <li class="comparison-item">
                                    <span class="comparison-icon">⏳</span>
                                    <span>{{
                                        t("landing.comparison.curseforge.item2")
                                    }}</span>
                                </li>
                                <li class="comparison-item">
                                    <span class="comparison-icon">❌</span>
                                    <span>{{
                                        t("landing.comparison.curseforge.item3")
                                    }}</span>
                                </li>
                            </ul>
                        </div>
                        <div class="comparison-card comparison-card-featured">
                            <div class="comparison-header">
                                <h3 class="comparison-title">
                                    {{
                                        t("landing.comparison.easycurse.title")
                                    }}
                                </h3>
                            </div>
                            <ul class="comparison-list">
                                <li class="comparison-item">
                                    <span class="comparison-icon">✓</span>
                                    <span>{{
                                        t("landing.comparison.easycurse.item1")
                                    }}</span>
                                </li>
                                <li class="comparison-item">
                                    <span class="comparison-icon">⚡</span>
                                    <span>{{
                                        t("landing.comparison.easycurse.item2")
                                    }}</span>
                                </li>
                                <li class="comparison-item">
                                    <span class="comparison-icon">📦</span>
                                    <span>{{
                                        t("landing.comparison.easycurse.item3")
                                    }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            <!-- How It Works Section -->
            <section id="how-it-works" class="section how-it-works">
                <div class="container">
                    <div class="section-header">
                        <div class="section-badge">
                            {{ t("landing.how_it_works.badge") }}
                        </div>
                        <h2 class="section-title">
                            {{ t("landing.how_it_works.title") }}
                        </h2>
                        <p class="section-subtitle">
                            {{ t("landing.how_it_works.subtitle") }}
                        </p>
                    </div>
                    <div class="steps-grid">
                        <div class="step-card">
                            <div class="step-icon-wrapper">
                                <div class="step-number">1</div>
                                <div class="step-connector"></div>
                            </div>
                            <div class="step-content">
                                <h3 class="step-title">
                                    {{ t("landing.how_it_works.step1.title") }}
                                </h3>
                                <p class="step-description">
                                    {{
                                        t(
                                            "landing.how_it_works.step1.description",
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                        <div class="step-card">
                            <div class="step-icon-wrapper">
                                <div class="step-number">2</div>
                                <div class="step-connector"></div>
                            </div>
                            <div class="step-content">
                                <h3 class="step-title">
                                    {{ t("landing.how_it_works.step2.title") }}
                                </h3>
                                <p class="step-description">
                                    {{
                                        t(
                                            "landing.how_it_works.step2.description",
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                        <div class="step-card">
                            <div class="step-icon-wrapper">
                                <div class="step-number">3</div>
                            </div>
                            <div class="step-content">
                                <h3 class="step-title">
                                    {{ t("landing.how_it_works.step3.title") }}
                                </h3>
                                <p class="step-description">
                                    {{
                                        t(
                                            "landing.how_it_works.step3.description",
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Benefits Section -->
            <section class="section benefits">
                <div class="container">
                    <div class="benefits-content">
                        <div class="benefits-text">
                            <div class="section-badge">
                                {{ t("landing.benefits.badge") }}
                            </div>
                            <h2 class="section-title">
                                {{ t("landing.benefits.title") }}
                            </h2>
                            <p class="section-subtitle">
                                {{ t("landing.benefits.subtitle") }}
                            </p>
                            <ul class="benefits-list">
                                <li class="benefit-item">
                                    <span class="benefit-icon">✓</span>
                                    <span>{{
                                        t("landing.benefits.item1")
                                    }}</span>
                                </li>
                                <li class="benefit-item">
                                    <span class="benefit-icon">✓</span>
                                    <span>{{
                                        t("landing.benefits.item2")
                                    }}</span>
                                </li>
                                <li class="benefit-item">
                                    <span class="benefit-icon">✓</span>
                                    <span>{{
                                        t("landing.benefits.item3")
                                    }}</span>
                                </li>
                                <li class="benefit-item">
                                    <span class="benefit-icon">✓</span>
                                    <span>{{
                                        t("landing.benefits.item4")
                                    }}</span>
                                </li>
                                <li class="benefit-item">
                                    <span class="benefit-icon">✓</span>
                                    <span>{{
                                        t("landing.benefits.item5")
                                    }}</span>
                                </li>
                            </ul>
                        </div>
                        <div class="benefits-visual">
                            <div class="visual-card">
                                <div class="visual-header">
                                    <div class="visual-dots">
                                        <span></span>
                                        <span></span>
                                        <span></span>
                                    </div>
                                </div>
                                <div class="visual-content">
                                    <div class="visual-item">
                                        <div class="visual-icon">📦</div>
                                        <div class="visual-text">
                                            <div class="visual-title">
                                                {{
                                                    t(
                                                        "landing.benefits.visual.modpack",
                                                    )
                                                }}
                                                v1.20.1
                                            </div>
                                            <div class="visual-subtitle">
                                                45
                                                {{
                                                    t(
                                                        "landing.benefits.visual.mods",
                                                    )
                                                }}
                                                •
                                                {{
                                                    t(
                                                        "landing.benefits.visual.ready",
                                                    )
                                                }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="visual-divider"></div>
                                    <div class="visual-item updating">
                                        <div class="visual-icon">🔄</div>
                                        <div class="visual-text">
                                            <div class="visual-title">
                                                {{
                                                    t(
                                                        "landing.benefits.visual.updating_to",
                                                    )
                                                }}
                                                v1.21...
                                            </div>
                                            <div class="visual-subtitle">
                                                {{
                                                    t(
                                                        "landing.benefits.visual.finding_versions",
                                                    )
                                                }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="visual-divider"></div>
                                    <div class="visual-item">
                                        <div class="visual-icon">✅</div>
                                        <div class="visual-text">
                                            <div class="visual-title">
                                                {{
                                                    t(
                                                        "landing.benefits.visual.modpack",
                                                    )
                                                }}
                                                v1.21
                                            </div>
                                            <div class="visual-subtitle">
                                                45
                                                {{
                                                    t(
                                                        "landing.benefits.visual.mods",
                                                    )
                                                }}
                                                •
                                                {{
                                                    t(
                                                        "landing.benefits.visual.updated",
                                                    )
                                                }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Stats Section -->
            <section class="section stats">
                <div class="container">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-number">
                                {{
                                    formatNumber(
                                        $page.props.stats.total_mod_packs,
                                    )
                                }}
                            </div>
                            <div class="stat-label">Mod Packs</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number">
                                {{
                                    formatNumber($page.props.stats.total_users)
                                }}
                            </div>
                            <div class="stat-label">Users</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number">
                                {{
                                    formatNumber(
                                        $page.props.stats.total_downloads,
                                    )
                                }}
                            </div>
                            <div class="stat-label">Downloads</div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Features Section -->
            <section class="section features">
                <div class="container">
                    <div class="section-header">
                        <div class="section-badge">
                            {{ t("landing.features.badge") }}
                        </div>
                        <h2 class="section-title">
                            {{ t("landing.features.title") }}
                        </h2>
                        <p class="section-subtitle">
                            {{ t("landing.features.subtitle") }}
                        </p>
                    </div>
                    <div class="features-grid">
                        <div class="feature-card">
                            <div class="feature-icon">🎮</div>
                            <h3 class="feature-title">
                                {{ t("landing.features.multiple.title") }}
                            </h3>
                            <p class="feature-description">
                                {{ t("landing.features.multiple.description") }}
                            </p>
                        </div>
                        <div class="feature-card">
                            <div class="feature-icon">🔄</div>
                            <h3 class="feature-title">
                                {{ t("landing.features.auto.title") }}
                            </h3>
                            <p class="feature-description">
                                {{ t("landing.features.auto.description") }}
                            </p>
                        </div>
                        <div class="feature-card">
                            <div class="feature-icon">📥</div>
                            <h3 class="feature-title">
                                {{ t("landing.features.download.title") }}
                            </h3>
                            <p class="feature-description">
                                {{ t("landing.features.download.description") }}
                            </p>
                        </div>
                        <div class="feature-card">
                            <div class="feature-icon">💾</div>
                            <h3 class="feature-title">
                                {{ t("landing.features.storage.title") }}
                            </h3>
                            <p class="feature-description">
                                {{ t("landing.features.storage.description") }}
                            </p>
                        </div>
                        <div class="feature-card">
                            <div class="feature-icon">🛡️</div>
                            <h3 class="feature-title">
                                {{ t("landing.features.updated.title") }}
                            </h3>
                            <p class="feature-description">
                                {{ t("landing.features.updated.description") }}
                            </p>
                        </div>
                        <div class="feature-card">
                            <div class="feature-icon">🔗</div>
                            <h3 class="feature-title">
                                {{ t("landing.features.share.title") }}
                            </h3>
                            <p class="feature-description">
                                {{ t("landing.features.share.description") }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- CTA Section -->
            <section class="section cta">
                <div class="container">
                    <div class="cta-content">
                        <h2 class="cta-title">
                            {{ t("landing.cta.title") }}
                        </h2>
                        <p class="cta-subtitle">
                            {{ t("landing.cta.subtitle") }}
                        </p>
                        <div class="cta-actions">
                            <Button
                                v-if="!$page.props.auth.user"
                                tag="Link"
                                href="/register"
                                size="large"
                                show-arrow
                            >
                                {{ t("landing.hero.get_started") }}
                            </Button>
                            <Button
                                v-else
                                tag="Link"
                                href="/mod-packs"
                                size="large"
                                show-arrow
                            >
                                {{ t("landing.hero.my_mods") }}
                            </Button>
                        </div>
                        <div class="cta-features">
                            <div class="cta-feature">
                                ✓ {{ t("landing.cta.feature1") }}
                            </div>
                            <div class="cta-feature">
                                ✓ {{ t("landing.cta.feature2") }}
                            </div>
                            <div class="cta-feature">
                                ✓ {{ t("landing.cta.feature3") }}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="cta-background">
                    <div class="cta-orb"></div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

<script setup>
import { Head } from "@inertiajs/vue3";
import AppLayout from "../Layouts/AppLayout.vue";
import Button from "../Components/Button.vue";
import { useTranslations } from "../composables/useTranslations";

const { t } = useTranslations();

const formatNumber = (num) => {
    if (num >= 1000000) {
        return (num / 1000000).toFixed(1) + "M";
    }
    if (num >= 1000) {
        return (num / 1000).toFixed(1) + "K";
    }
    return num.toString();
};
</script>
