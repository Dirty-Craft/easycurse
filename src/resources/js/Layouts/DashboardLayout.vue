<template>
    <div class="dashboard-layout">
        <!-- Mobile Overlay -->
        <div
            v-if="isMobileMenuOpen"
            class="sidebar-overlay"
            @click="closeMobileMenu"
        ></div>

        <aside class="dashboard-sidebar" :class="{ open: isMobileMenuOpen }">
            <div class="sidebar-header">
                <Link
                    href="/dashboard"
                    class="sidebar-logo"
                    @click="closeMobileMenu"
                >
                    <span class="logo-text">CurseCool</span>
                </Link>
            </div>

            <nav class="sidebar-nav">
                <Link
                    href="/dashboard"
                    class="sidebar-item"
                    :class="{ active: $page.url === '/dashboard' }"
                    @click="closeMobileMenu"
                >
                    <span class="sidebar-icon">📊</span>
                    <span class="sidebar-label">Dashboard</span>
                </Link>
                <Link
                    href="/mod-sets"
                    class="sidebar-item"
                    :class="{ active: $page.url.startsWith('/mod-sets') }"
                    @click="closeMobileMenu"
                >
                    <span class="sidebar-icon">📦</span>
                    <span class="sidebar-label">My Mods</span>
                </Link>
            </nav>

            <div class="sidebar-footer">
                <form class="logout-form" @submit.prevent="logout">
                    <button
                        type="submit"
                        class="sidebar-item sidebar-logout"
                        @click="closeMobileMenu"
                    >
                        <span class="sidebar-icon">🚪</span>
                        <span class="sidebar-label">Logout</span>
                    </button>
                </form>
            </div>
        </aside>

        <main class="dashboard-main-wrapper">
            <!-- Mobile Header with Hamburger -->
            <div class="dashboard-mobile-header">
                <button
                    class="hamburger-button"
                    aria-label="Toggle menu"
                    @click="toggleMobileMenu"
                >
                    <span
                        class="hamburger-icon"
                        :class="{ open: isMobileMenuOpen }"
                    >
                        <span></span>
                        <span></span>
                        <span></span>
                    </span>
                </button>
                <Link href="/dashboard" class="mobile-logo">
                    <span class="logo-text">CurseCool</span>
                </Link>
            </div>

            <slot />
        </main>
    </div>
</template>

<script setup>
import { Link, router } from "@inertiajs/vue3";
import { ref, onMounted, onUnmounted } from "vue";

const isMobileMenuOpen = ref(false);

const toggleMobileMenu = () => {
    isMobileMenuOpen.value = !isMobileMenuOpen.value;
};

const closeMobileMenu = () => {
    isMobileMenuOpen.value = false;
};

// Close menu on escape key
const handleEscape = (e) => {
    if (e.key === "Escape" && isMobileMenuOpen.value) {
        closeMobileMenu();
    }
};

// Close menu when clicking outside (handled by overlay)
// Close menu on window resize if it becomes desktop size
const handleResize = () => {
    if (window.innerWidth > 640 && isMobileMenuOpen.value) {
        closeMobileMenu();
    }
};

onMounted(() => {
    window.addEventListener("keydown", handleEscape);
    window.addEventListener("resize", handleResize);
});

onUnmounted(() => {
    window.removeEventListener("keydown", handleEscape);
    window.removeEventListener("resize", handleResize);
});

const logout = () => {
    router.post("/logout");
};
</script>
