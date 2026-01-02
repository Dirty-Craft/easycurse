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
                        <Link
                            v-if="$page.props.auth.user"
                            href="/mod-packs"
                            class="btn btn-primary"
                        >
                            My Mods
                        </Link>
                        <template v-if="$page.props.auth.user">
                            <form class="logout-form" @submit.prevent="logout">
                                <button type="submit" class="btn btn-secondary">
                                    Logout
                                </button>
                            </form>
                        </template>
                        <Link v-else href="/login" class="btn btn-secondary">
                            Login
                        </Link>
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
                </div>
            </div>
        </footer>
    </div>
</template>

<script setup>
import { Link, router } from "@inertiajs/vue3";

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
