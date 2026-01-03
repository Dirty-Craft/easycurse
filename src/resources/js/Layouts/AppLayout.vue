<template>
    <div class="app-layout">
        <!-- Navigation -->
        <nav class="navbar">
            <div class="container">
                <div class="nav-content">
                    <Link href="/" class="logo">
                        <span class="logo-text">EasyCurse</span>
                    </Link>
                    <div class="nav-actions">
                        <LanguageSwitcher />
                        <ThemeSwitcher />
                        <Button
                            v-if="$page.props.auth.user"
                            tag="Link"
                            href="/mod-packs"
                        >
                            {{ t("layout.my_mods") }}
                        </Button>
                        <template v-if="$page.props.auth.user">
                            <form class="logout-form" @submit.prevent="logout">
                                <Button type="submit" variant="secondary">
                                    {{ t("layout.logout") }}
                                </Button>
                            </form>
                        </template>
                        <Button
                            v-else
                            tag="Link"
                            href="/login"
                            variant="secondary"
                        >
                            {{ t("layout.login") }}
                        </Button>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <slot />
        </main>

        <!-- Footer -->
        <footer class="footer">
            <div class="container">
                <div class="footer-content">
                    <div class="footer-brand">
                        <span class="logo-text">EasyCurse</span>
                        <p class="footer-tagline">
                            {{ t("layout.footer.tagline") }}
                        </p>
                    </div>
                    <div class="footer-links">
                        <div class="footer-column">
                            <h3 class="footer-heading">
                                {{ t("layout.footer.info") }}
                            </h3>
                            <Link href="/about" class="footer-link">
                                {{ t("layout.footer.about") }}
                            </Link>
                            <a
                                href="https://github.com/Dirty-Craft/easycurse"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="footer-link"
                            >
                                {{ t("layout.footer.github") }}
                            </a>
                        </div>
                        <div v-if="$page.props.auth.user" class="footer-column">
                            <h3 class="footer-heading">
                                {{ t("layout.footer.my_account") }}
                            </h3>
                            <Link href="/profile" class="footer-link">
                                {{ t("layout.footer.profile") }}
                            </Link>
                            <Link href="/change-password" class="footer-link">
                                {{ t("layout.footer.change_password") }}
                            </Link>
                        </div>
                    </div>
                </div>
                <div class="footer-bottom">
                    <p>
                        {{
                            t("layout.footer.copyright", {
                                year: new Date().getFullYear(),
                            })
                        }}
                        <a
                            href="https://opensource.org/licenses/MIT"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="footer-link"
                        >
                            {{ t("layout.footer.mit") }}
                        </a>
                        .
                    </p>
                </div>
            </div>
        </footer>
    </div>
</template>

<script setup>
import { Link, router } from "@inertiajs/vue3";
import { onMounted } from "vue";
import Button from "../Components/Button.vue";
import LanguageSwitcher from "../Components/LanguageSwitcher.vue";
import ThemeSwitcher from "../Components/ThemeSwitcher.vue";
import { useTheme } from "../composables/useTheme";
import { useTranslations } from "../composables/useTranslations";
import { useFont } from "../composables/useFont";
import { useDirection } from "../composables/useDirection";

const { t } = useTranslations();

// Initialize font and direction when layout mounts
useFont();
useDirection();

const logout = () => {
    if (!confirm("Are you sure you want to logout?")) {
        return;
    }
    router.post("/logout");
};

// Initialize theme when layout mounts
const { initTheme } = useTheme();
onMounted(() => {
    initTheme();
});
</script>

<style scoped>
.app-layout {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    background-color: var(--color-background);
    color: var(--color-text-primary);
}

.main-content {
    flex: 1;
    padding-top: 80px; /* Account for fixed navbar */
}

.logout-form {
    display: inline-block;
    margin: 0;
}
</style>
