<template>
    <Head title="Register" />
    <AppLayout>
        <div class="auth-page">
            <div class="auth-container">
                <div class="auth-card">
                    <div class="auth-header">
                        <h1 class="auth-title">Create Account</h1>
                        <p class="auth-subtitle">
                            Sign up to get started with EasyCurse
                        </p>
                    </div>

                    <form class="auth-form" @submit.prevent="submit">
                        <FormGroup
                            label="Name"
                            input-id="name"
                            :error="form.errors.name"
                        >
                            <Input
                                id="name"
                                v-model="form.name"
                                type="text"
                                required
                                autofocus
                                autocomplete="name"
                            />
                        </FormGroup>

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
                                autocomplete="email"
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
                                    ? "Creating account..."
                                    : "Create Account"
                            }}
                        </Button>
                    </form>

                    <div class="auth-footer">
                        <p>
                            Already have an account?
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

const form = useForm({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
});

const submit = () => {
    form.post("/register", {
        onFinish: () => form.reset("password", "password_confirmation"),
    });
};
</script>
