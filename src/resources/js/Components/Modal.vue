<template>
    <Teleport to="body">
        <div v-if="show" class="modal-overlay" @click.self="handleClose">
            <div
                :class="[
                    'modal-content',
                    size === 'large' && 'modal-content-large',
                    size === 'fullscreen' && 'modal-content-fullscreen',
                ]"
                @click.stop
            >
                <div class="modal-header">
                    <h2 v-if="title">{{ title }}</h2>
                    <slot name="header" />
                    <button
                        v-if="closable"
                        class="modal-close"
                        aria-label="Close modal"
                        @click="handleClose"
                    >
                        ×
                    </button>
                </div>
                <div class="modal-body">
                    <slot />
                </div>
                <div v-if="$slots.footer" class="modal-footer">
                    <slot name="footer" />
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: "",
    },
    closable: {
        type: Boolean,
        default: true,
    },
    size: {
        type: String,
        default: "default",
        validator: (value) =>
            ["default", "large", "fullscreen"].includes(value),
    },
});

const emit = defineEmits(["update:show", "close"]);

const handleClose = () => {
    emit("update:show", false);
    emit("close");
};
</script>
