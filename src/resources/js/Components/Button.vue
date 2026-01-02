<template>
    <component
        :is="componentTag"
        :class="buttonClasses"
        :disabled="disabled"
        :type="componentTag === 'button' ? type : undefined"
        v-bind="$attrs"
    >
        <slot />
        <span v-if="showArrow" class="btn-arrow">→</span>
    </component>
</template>

<script setup>
import { computed } from "vue";
import { Link } from "@inertiajs/vue3";

const props = defineProps({
    variant: {
        type: String,
        default: "primary",
        validator: (value) =>
            ["primary", "secondary", "danger", "success"].includes(value),
    },
    size: {
        type: String,
        default: "md",
        validator: (value) => ["sm", "md", "large"].includes(value),
    },
    full: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    showArrow: {
        type: Boolean,
        default: false,
    },
    tag: {
        type: String,
        default: "button",
        validator: (value) => ["button", "a", "Link"].includes(value),
    },
    type: {
        type: String,
        default: "button",
    },
});

const componentTag = computed(() => {
    if (props.tag === "Link") return Link;
    return props.tag;
});

const buttonClasses = computed(() => {
    return [
        "btn",
        `btn-${props.variant}`,
        props.size !== "md" && `btn-${props.size}`,
        props.full && "btn-full",
    ].filter(Boolean);
});
</script>
