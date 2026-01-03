<template>
    <Head title="Reset Password" />
    <AppLayout>
        <div class="auth-page">
            <div class="auth-container">
                <div class="auth-card">
                    <div class="auth-header">
                        <h1 class="auth-title">Reset Password</h1>
                        <p class="auth-subtitle">
                            Enter your new password below
                        </p>
                    </div>

                    <form class="auth-form" @submit.prevent="submit">
                        <input v-model="form.token" type="hidden" />
                        <input v-model="form.email" type="hidden" />

                        <FormGroup
                            label="Email"
                            input-id="email"
                            :error="form.errors.email"
                        >
                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                                required
                                autofocus
                                autocomplete="email"
                                :disabled="true"
                            />
                        </FormGroup>

                        <FormGroup
                            label="Password"
                            input-id="password"
                            :error="form.errors.password"
                        >
                            <Input
                                id="password"
                                v-model="form.password"
                                type="password"
                                required
                                autocomplete="new-password"
                            />
                        </FormGroup>

                        <FormGroup
                            label="Confirm Password"
                            input-id="password_confirmation"
                            :error="form.errors.password_confirmation"
                        >
                            <Input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                type="password"
                                required
                                autocomplete="new-password"
                            />
                        </FormGroup>

                        <Button type="submit" full :disabled="form.processing">
                            {{
                                form.processing
                                    ? "Resetting..."
                                    : "Reset Password"
                            }}
                        </Button>
                    </form>

                    <div class="auth-footer">
                        <p>
                            Remember your password?
                            <Link href="/login" class="auth-link">Sign in</Link>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";
import AppLayout from "../../Layouts/AppLayout.vue";
import Button from "../../Components/Button.vue";
import Input from "../../Components/Input.vue";
import FormGroup from "../../Components/FormGroup.vue";

const props = defineProps({
    token: {
        type: String,
        required: true,
    },
    email: {
        type: String,
        required: true,
    },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: "",
    password_confirmation: "",
});

const submit = () => {
    form.post("/reset-password", {
        onFinish: () => form.reset("password", "password_confirmation"),
    });
};
</script>
