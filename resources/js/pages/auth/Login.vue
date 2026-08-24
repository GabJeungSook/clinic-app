<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';

defineOptions({
    layout: {
        title: 'Sign in',
        description: 'Enter your username and password to continue',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Sign in" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-5">
            <div class="grid gap-2">
                <Label for="username">Username</Label>
                <Input
                    id="username"
                    type="text"
                    name="username"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="username"
                    placeholder="Enter your username"
                />
                <InputError :message="errors.username" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Password</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="Password"
                />
                <InputError :message="errors.password" />
            </div>

            <Label for="remember" class="flex items-center space-x-3">
                <Checkbox id="remember" name="remember" :tabindex="3" />
                <span class="text-sm">Remember me</span>
            </Label>

            <Button
                type="submit"
                class="mt-2 w-full"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Sign in
            </Button>
        </div>
    </Form>
</template>
