<template>
    <Head title="About Us" />
    <AppLayout>
        <div class="about-page">
            <section class="about-hero">
                <div class="container">
                    <div class="about-content">
                        <h1 class="about-title">About EasyCurse</h1>
                        <p class="about-subtitle">
                            Your mod management companion. Never manually update
                            mods again.
                        </p>
                    </div>
                </div>
            </section>

            <section class="about-section">
                <div class="container">
                    <div class="about-text">
                        <h2 class="section-title">Our Mission</h2>
                        <p>
                            EasyCurse was born from the frustration of manually
                            updating Minecraft mods every time a new version
                            releases. We believe that managing your mod packs
                            should be simple, fast, and automated.
                        </p>
                        <p>
                            Our platform connects directly to CurseForge's API,
                            allowing you to create mod packs once and update
                            them instantly whenever Minecraft releases a new
                            version. No more searching through hundreds of mod
                            pages, no more compatibility headaches, no more
                            manual downloads.
                        </p>
                    </div>
                </div>
            </section>

            <section class="about-section">
                <div class="container">
                    <div class="about-text">
                        <h2 class="section-title">What We Do</h2>
                        <p>
                            EasyCurse helps Minecraft players and server
                            administrators manage their mod collections
                            effortlessly. Create unlimited mod packs, track
                            updates, download everything as a ready-to-use zip
                            file, and share your creations with friends and the
                            community.
                        </p>
                        <p>
                            Whether you're running a private server, creating
                            content for your community, sharing mod packs with
                            others, or just want to keep your personal mod
                            collection up to date, EasyCurse makes it all
                            possible with just a few clicks.
                        </p>
                    </div>
                </div>
            </section>

            <section class="about-section">
                <div class="container">
                    <div class="about-text">
                        <h2 class="section-title">Open Source</h2>
                        <p>
                            EasyCurse is an open-source project developed by
                            <a
                                href="https://github.com/Dirty-Craft"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="link"
                            >
                                Dirty-Craft
                            </a>
                            . We believe in transparency and community
                            collaboration.
                        </p>
                        <p>
                            You can find our source code on
                            <a
                                href="https://github.com/Dirty-Craft/easycurse"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="link"
                            >
                                GitHub
                            </a>
                            , contribute to the project, report issues, or
                            suggest new features. We welcome all contributions!
                        </p>
                        <p>
                            This project is licensed under the
                            <a
                                href="https://opensource.org/licenses/MIT"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="link"
                            >
                                MIT License
                            </a>
                            .
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
