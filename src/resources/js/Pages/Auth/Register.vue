<template>
    <Head title="Register" />
    <div class="auth-page">
        <div class="auth-container">
            <div class="auth-card">
                <div class="auth-header">
                    <h1 class="auth-title">Create Account</h1>
                    <p class="auth-subtitle">
                        Sign up to get started with CurseCool
                    </p>
                </div>

                <form class="auth-form" @submit.prevent="submit">
                    <div class="form-group">
                        <label for="name" class="form-label">Name</label>
                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            class="form-input"
                            required
                            autofocus
                            autocomplete="name"
                        />
                        <div v-if="form.errors.name" class="form-error">
                            {{ form.errors.name }}
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email" class="form-label">Email</label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            class="form-input"
                            required
                            autocomplete="email"
                        />
                        <div v-if="form.errors.email" class="form-error">
                            {{ form.errors.email }}
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label"
                            >Password</label
                        >
                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            class="form-input"
                            required
                            autocomplete="new-password"
                        />
                        <div v-if="form.errors.password" class="form-error">
                            {{ form.errors.password }}
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password_confirmation" class="form-label"
                            >Confirm Password</label
                        >
                        <input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            class="form-input"
                            required
                            autocomplete="new-password"
                        />
                    </div>

                    <button
                        type="submit"
                        class="btn btn-primary btn-full"
                        :disabled="form.processing"
                    >
                        {{
                            form.processing
                                ? "Creating account..."
                                : "Create Account"
                        }}
                    </button>
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
</template>

<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";

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
