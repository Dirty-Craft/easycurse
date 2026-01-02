<template>
    <Head title="Login" />
    <AppLayout>
        <div class="auth-page">
            <div class="auth-container">
                <div class="auth-card">
                    <div class="auth-header">
                        <h1 class="auth-title">Welcome Back</h1>
                        <p class="auth-subtitle">
                            Sign in to your account to continue
                        </p>
                    </div>

                    <form class="auth-form" @submit.prevent="submit">
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
                                autocomplete="current-password"
                            />
                        </FormGroup>

                        <div class="form-group">
                            <Checkbox v-model="form.remember">
                                Remember me
                            </Checkbox>
                        </div>

                        <Button type="submit" full :disabled="form.processing">
                            {{ form.processing ? "Signing in..." : "Sign In" }}
                        </Button>
                    </form>

                    <div class="auth-footer">
                        <p>
                            Don't have an account?
                            <Link href="/register" class="auth-link"
                                >Sign up</Link
                            >
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
import Checkbox from "../../Components/Checkbox.vue";

const form = useForm({
    email: "",
    password: "",
    remember: false,
});

const submit = () => {
    form.post("/login", {
        onFinish: () => form.reset("password"),
    });
};
</script>
