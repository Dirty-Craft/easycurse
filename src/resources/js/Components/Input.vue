<template>
    <component
        :is="inputTag"
        :id="id"
        :type="inputType"
        :value="modelValue"
        :placeholder="placeholder"
        :required="required"
        :disabled="disabled"
        :autocomplete="autocomplete"
        :autofocus="autofocus"
        :rows="rows"
        :class="['form-input', inputClass]"
        v-bind="$attrs"
        @input="handleInput"
        @change="handleChange"
        @blur="$emit('blur', $event)"
        @focus="$emit('focus', $event)"
    >
        <template v-if="inputTag === 'select'">
            <slot />
        </template>
    </component>
</template>

<script setup>
import { computed } from "vue";

const props = defineProps({
    modelValue: {
        type: [String, Number],
        default: "",
    },
    type: {
        type: String,
        default: "text",
        validator: (value) =>
            [
                "text",
                "email",
                "password",
                "number",
                "tel",
                "url",
                "textarea",
                "select",
            ].includes(value),
    },
    id: {
        type: String,
        default: "",
    },
    placeholder: {
        type: String,
        default: "",
    },
    required: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    autocomplete: {
        type: String,
        default: "",
    },
    autofocus: {
        type: Boolean,
        default: false,
    },
    rows: {
        type: Number,
        default: 3,
    },
    inputClass: {
        type: String,
        default: "",
    },
});

const emit = defineEmits(["update:modelValue", "blur", "focus"]);

const inputTag = computed(() => {
    if (props.type === "textarea") return "textarea";
    if (props.type === "select") return "select";
    return "input";
});

const inputType = computed(() => {
    if (props.type === "textarea" || props.type === "select") return undefined;
    return props.type;
});

const handleInput = (event) => {
    const value = event.target.value;
    emit("update:modelValue", value);
};

const handleChange = (event) => {
    if (props.type === "select") {
        const value = event.target.value;
        emit("update:modelValue", value);
    }
};
</script>
