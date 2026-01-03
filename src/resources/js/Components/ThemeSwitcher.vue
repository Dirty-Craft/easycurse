<template>
    <button
        class="theme-switcher"
        :aria-label="`Switch to ${currentTheme === 'dark' ? 'light' : 'dark'} theme`"
        type="button"
        @click="toggleTheme"
    >
        <span v-if="currentTheme === 'dark'" class="theme-icon">
            <svg
                xmlns="http://www.w3.org/2000/svg"
                width="20"
                height="20"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <circle cx="12" cy="12" r="5"></circle>
                <line x1="12" y1="1" x2="12" y2="3"></line>
                <line x1="12" y1="21" x2="12" y2="23"></line>
                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                <line x1="1" y1="12" x2="3" y2="12"></line>
                <line x1="21" y1="12" x2="23" y2="12"></line>
                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
            </svg>
        </span>
        <span v-else class="theme-icon">
            <svg
                xmlns="http://www.w3.org/2000/svg"
                width="20"
                height="20"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <path
                    d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"
                ></path>
            </svg>
        </span>
    </button>
</template>

<script setup>
import { useTheme } from "../composables/useTheme";

const { currentTheme, toggleTheme } = useTheme();
</script>

<style scoped>
.theme-switcher {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border: 1px solid var(--color-border);
    background: var(--color-surface);
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: all var(--transition-base);
    color: var(--color-text-primary);
    padding: 0;
    margin: 0;
}

.theme-switcher:hover {
    background: var(--color-background-light);
    border-color: var(--color-primary);
    color: var(--color-primary);
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.theme-switcher:active {
    transform: translateY(0);
}

.theme-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform var(--transition-base);
}

.theme-switcher:hover .theme-icon {
    transform: rotate(15deg);
}
</style>
