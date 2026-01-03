<template>
    <div ref="dropdownRef" class="language-switcher">
        <button
            class="lang-trigger"
            type="button"
            :aria-label="`Current language: ${currentLocale.toUpperCase()}`"
            :aria-expanded="isOpen"
            @click="toggleDropdown"
        >
            <span class="lang-code">{{ currentLocale.toUpperCase() }}</span>
            <svg
                class="dropdown-icon"
                :class="{ open: isOpen }"
                xmlns="http://www.w3.org/2000/svg"
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
        </button>
        <div v-if="isOpen" class="lang-dropdown">
            <a
                v-for="lang in languages"
                :key="lang.code"
                :href="getLanguageUrl(lang.code)"
                class="lang-option"
                :class="{ active: currentLocale === lang.code }"
            >
                <span class="lang-code">{{ lang.code.toUpperCase() }}</span>
                <span class="lang-name">{{ lang.name }}</span>
                <svg
                    v-if="currentLocale === lang.code"
                    class="check-icon"
                    xmlns="http://www.w3.org/2000/svg"
                    width="16"
                    height="16"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
            </a>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted } from "vue";
import { usePage } from "@inertiajs/vue3";

const page = usePage();
const currentLocale = computed(() => page.props.locale || "en");
const isOpen = ref(false);
const dropdownRef = ref(null);

const languages = [
    { code: "en", name: "English" },
    { code: "fa", name: "فارسی" },
];

const toggleDropdown = () => {
    isOpen.value = !isOpen.value;
};

const closeDropdown = () => {
    isOpen.value = false;
};

const getLanguageUrl = (lang) => {
    // Get current URL and query parameters
    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set("lang", lang);

    // Return the full URL with the language parameter
    return currentUrl.pathname + currentUrl.search;
};

// Handle click outside
const handleClickOutside = (event) => {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
        closeDropdown();
    }
};

// Close dropdown on escape key
const handleEscape = (e) => {
    if (e.key === "Escape" && isOpen.value) {
        closeDropdown();
    }
};

onMounted(() => {
    document.addEventListener("keydown", handleEscape);
    document.addEventListener("click", handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener("keydown", handleEscape);
    document.removeEventListener("click", handleClickOutside);
});
</script>

<style scoped>
.language-switcher {
    position: relative;
    display: inline-block;
}

.lang-trigger {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border: 1px solid var(--color-border);
    background: var(--color-surface);
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: all var(--transition-base);
    color: var(--color-text-primary);
    font-size: 0.875rem;
    font-weight: 500;
    min-width: 70px;
    justify-content: space-between;
}

.lang-trigger:hover {
    background: var(--color-background-light);
    border-color: var(--color-primary);
    color: var(--color-primary);
}

.lang-trigger:active {
    transform: scale(0.98);
}

.lang-code {
    font-weight: 600;
}

.dropdown-icon {
    transition: transform var(--transition-base);
    flex-shrink: 0;
}

.dropdown-icon.open {
    transform: rotate(180deg);
}

.lang-dropdown {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    min-width: 140px;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-lg);
    overflow: hidden;
    z-index: 1100;
    animation: slideDown 0.2s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.lang-option {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    padding: 10px 16px;
    border: none;
    background: transparent;
    color: var(--color-text-primary);
    font-size: 0.875rem;
    text-align: left;
    cursor: pointer;
    transition: all var(--transition-base);
    text-decoration: none;
}

.lang-option:hover {
    background: var(--color-background-light);
}

.lang-option.active {
    background: var(--color-primary);
    color: white;
    font-weight: 600;
}

.lang-option .lang-code {
    min-width: 32px;
    font-weight: 600;
}

.lang-option .lang-name {
    flex: 1;
}

.check-icon {
    flex-shrink: 0;
    margin-left: auto;
}

@media (width <= 640px) {
    .lang-trigger {
        min-width: 50px;
        padding: 6px 8px;
        gap: 4px;
        font-size: 0.75rem;
    }
}
</style>
