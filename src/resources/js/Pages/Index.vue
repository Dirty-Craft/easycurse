<template>
    <Head title="Your mod management companion" />
    <AppLayout>
        <div class="landing-page">
            <!-- Hero Section -->
            <section class="hero">
                <div class="container">
                    <div class="hero-content">
                        <div class="hero-badge">
                            <span class="badge-icon">✨</span>
                            <span>No more manual mod updates</span>
                        </div>
                        <h1 class="hero-title">
                            Never Manually Update
                            <span class="gradient-text">Minecraft Mods</span>
                            Again
                        </h1>
                        <p class="hero-subtitle">
                            Create mod packs once. Update Minecraft versions
                            instantly. Download your updated mod pack as a zip.
                            Simple as that.
                        </p>
                        <div class="hero-actions">
                            <Button
                                v-if="!$page.props.auth.user"
                                tag="Link"
                                href="/register"
                                show-arrow
                            >
                                Get Started Free
                            </Button>
                            <Button
                                v-else
                                tag="Link"
                                href="/mod-packs"
                                show-arrow
                            >
                                My Mods
                            </Button>
                            <Button
                                tag="a"
                                href="#how-it-works"
                                variant="secondary"
                            >
                                Learn More
                            </Button>
                        </div>
                        <div class="hero-stats">
                            <div class="stat-item">
                                <div class="stat-number">∞</div>
                                <div class="stat-label">
                                    Unlimited Mod Packs
                                </div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number">⚡</div>
                                <div class="stat-label">Instant Updates</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number">📦</div>
                                <div class="stat-label">One-Click Download</div>
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

            <!-- How It Works Section -->
            <section id="how-it-works" class="section how-it-works">
                <div class="container">
                    <div class="section-header">
                        <div class="section-badge">Simple Process</div>
                        <h2 class="section-title">How It Works</h2>
                        <p class="section-subtitle">
                            Three simple steps to keep your mods updated forever
                        </p>
                    </div>
                    <div class="steps-grid">
                        <div class="step-card">
                            <div class="step-icon-wrapper">
                                <div class="step-number">1</div>
                                <div class="step-connector"></div>
                            </div>
                            <div class="step-content">
                                <h3 class="step-title">Create Your Mod Pack</h3>
                                <p class="step-description">
                                    Log in and create a mod pack with all your
                                    favorite CurseForge mods. Organize them
                                    however you like. Save multiple sets for
                                    different playstyles.
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
                                    Change Minecraft Version
                                </h3>
                                <p class="step-description">
                                    When a new Minecraft version drops, simply
                                    update the version in your mod pack. We'll
                                    automatically find compatible versions for
                                    all your mods.
                                </p>
                            </div>
                        </div>
                        <div class="step-card">
                            <div class="step-icon-wrapper">
                                <div class="step-number">3</div>
                            </div>
                            <div class="step-content">
                                <h3 class="step-title">Download & Play</h3>
                                <p class="step-description">
                                    Get your updated mod pack as a zip file.
                                    Extract and play. No manual searching, no
                                    version checking, no hassle.
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
                            <div class="section-badge">Why Choose Us</div>
                            <h2 class="section-title">
                                Save Hours of Manual Work
                            </h2>
                            <p class="section-subtitle">
                                Stop wasting time searching for compatible mod
                                versions. EasyCurse does the heavy lifting for
                                you.
                            </p>
                            <ul class="benefits-list">
                                <li class="benefit-item">
                                    <span class="benefit-icon">✓</span>
                                    <span
                                        >Automatic version matching from
                                        CurseForge</span
                                    >
                                </li>
                                <li class="benefit-item">
                                    <span class="benefit-icon">✓</span>
                                    <span
                                        >Update entire mod packs in
                                        seconds</span
                                    >
                                </li>
                                <li class="benefit-item">
                                    <span class="benefit-icon">✓</span>
                                    <span
                                        >Cloud storage for all your mod
                                        packs</span
                                    >
                                </li>
                                <li class="benefit-item">
                                    <span class="benefit-icon">✓</span>
                                    <span
                                        >One-click download as ready-to-use
                                        zip</span
                                    >
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
                                                Mod Pack v1.20.1
                                            </div>
                                            <div class="visual-subtitle">
                                                45 mods • Ready
                                            </div>
                                        </div>
                                    </div>
                                    <div class="visual-divider"></div>
                                    <div class="visual-item updating">
                                        <div class="visual-icon">🔄</div>
                                        <div class="visual-text">
                                            <div class="visual-title">
                                                Updating to v1.21...
                                            </div>
                                            <div class="visual-subtitle">
                                                Finding compatible versions
                                            </div>
                                        </div>
                                    </div>
                                    <div class="visual-divider"></div>
                                    <div class="visual-item">
                                        <div class="visual-icon">✅</div>
                                        <div class="visual-text">
                                            <div class="visual-title">
                                                Mod Pack v1.21
                                            </div>
                                            <div class="visual-subtitle">
                                                45 mods • Updated
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Features Section -->
            <section class="section features">
                <div class="container">
                    <div class="section-header">
                        <div class="section-badge">Features</div>
                        <h2 class="section-title">Everything You Need</h2>
                        <p class="section-subtitle">
                            Powerful features to manage your mod collection
                            effortlessly
                        </p>
                    </div>
                    <div class="features-grid">
                        <div class="feature-card">
                            <div class="feature-icon">🎮</div>
                            <h3 class="feature-title">Multiple Mod Packs</h3>
                            <p class="feature-description">
                                Create unlimited mod packs for different
                                playstyles, servers, or versions. Organize
                                everything in one place.
                            </p>
                        </div>
                        <div class="feature-card">
                            <div class="feature-icon">🔄</div>
                            <h3 class="feature-title">Auto Version Matching</h3>
                            <p class="feature-description">
                                We automatically find compatible mod versions
                                when you update Minecraft. No more manual
                                searching.
                            </p>
                        </div>
                        <div class="feature-card">
                            <div class="feature-icon">📥</div>
                            <h3 class="feature-title">One-Click Download</h3>
                            <p class="feature-description">
                                Download your entire mod pack as a single zip
                                file, ready to extract and play. It's that
                                simple.
                            </p>
                        </div>
                        <div class="feature-card">
                            <div class="feature-icon">💾</div>
                            <h3 class="feature-title">Cloud Storage</h3>
                            <p class="feature-description">
                                Your mod packs are saved in the cloud. Access
                                them anytime, anywhere, from any device.
                            </p>
                        </div>
                        <div class="feature-card">
                            <div class="feature-icon">⚡</div>
                            <h3 class="feature-title">Lightning Fast</h3>
                            <p class="feature-description">
                                Update entire mod packs in seconds, not hours of
                                manual work. Get back to playing faster.
                            </p>
                        </div>
                        <div class="feature-card">
                            <div class="feature-icon">🛡️</div>
                            <h3 class="feature-title">Always Up-to-Date</h3>
                            <p class="feature-description">
                                Get the latest compatible versions automatically
                                from CurseForge. Stay current with every update.
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
                            Ready to Simplify Your Mod Life?
                        </h2>
                        <p class="cta-subtitle">
                            Join EasyCurse today and never manually update mods
                            again. Start managing your mod collection the smart
                            way.
                        </p>
                        <div class="cta-actions">
                            <Button
                                v-if="!$page.props.auth.user"
                                tag="Link"
                                href="/register"
                                size="large"
                                show-arrow
                            >
                                Get Started Free
                            </Button>
                            <Button
                                v-else
                                tag="Link"
                                href="/mod-packs"
                                size="large"
                                show-arrow
                            >
                                My Mods
                            </Button>
                        </div>
                        <div class="cta-features">
                            <div class="cta-feature">✓ Free to start</div>
                            <div class="cta-feature">
                                ✓ No credit card required
                            </div>
                            <div class="cta-feature">✓ Setup in minutes</div>
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
</script>
