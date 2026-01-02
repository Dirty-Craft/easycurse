<template>
    <label class="form-checkbox">
        <input
            :id="id"
            :checked="modelValue"
            type="checkbox"
            :disabled="disabled"
            v-bind="$attrs"
            @change="handleChange"
        />
        <span><slot /></span>
    </label>
</template>

<script setup>
defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
    id: {
        type: String,
        default: "",
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["update:modelValue"]);

const handleChange = (event) => {
    emit("update:modelValue", event.target.checked);
};
</script>
