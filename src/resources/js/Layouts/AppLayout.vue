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
                        <Button
                            v-if="$page.props.auth.user"
                            tag="Link"
                            href="/mod-packs"
                        >
                            My Mods
                        </Button>
                        <template v-if="$page.props.auth.user">
                            <form class="logout-form" @submit.prevent="logout">
                                <Button type="submit" variant="secondary">
                                    Logout
                                </Button>
                            </form>
                        </template>
                        <Button
                            v-else
                            tag="Link"
                            href="/login"
                            variant="secondary"
                        >
                            Login
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
                            Your mod management companion. Never manually update
                            mods again.
                        </p>
                    </div>
                    <div class="footer-links">
                        <div class="footer-column">
                            <h3 class="footer-heading">Info</h3>
                            <Link href="/about" class="footer-link">
                                About Us
                            </Link>
                            <a
                                href="https://github.com/Dirty-Craft/easycurse"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="footer-link"
                            >
                                GitHub
                            </a>
                        </div>
                        <div v-if="$page.props.auth.user" class="footer-column">
                            <h3 class="footer-heading">My Account</h3>
                            <Link href="/profile" class="footer-link">
                                My Profile
                            </Link>
                            <Link href="/change-password" class="footer-link">
                                Change Password
                            </Link>
                        </div>
                    </div>
                </div>
                <div class="footer-bottom">
                    <p>
                        © {{ new Date().getFullYear() }} EasyCurse. Licensed
                        under
                        <a
                            href="https://opensource.org/licenses/MIT"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="footer-link"
                        >
                            MIT License
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
import Button from "../Components/Button.vue";

const logout = () => {
    router.post("/logout");
};
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
