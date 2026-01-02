<template>
    <div class="form-group">
        <label v-if="label" :for="inputId" class="form-label">
            {{ label }}
        </label>
        <slot />
        <div v-if="error" class="form-error">
            {{ error }}
        </div>
    </div>
</template>

<script setup>
defineProps({
    label: {
        type: String,
        default: "",
    },
    error: {
        type: String,
        default: "",
    },
    inputId: {
        type: String,
        default: "",
    },
});
</script>
