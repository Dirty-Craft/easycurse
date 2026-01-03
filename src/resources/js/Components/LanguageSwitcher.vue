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
