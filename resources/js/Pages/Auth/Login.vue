<template>
    <div class="min-h-screen bg-brand-black flex items-center justify-center p-4">
        <div class="w-full max-w-sm">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-white">WhiteJersey <span class="text-brand-red">Cafe</span></h1>
                <p class="text-brand-gray-mid mt-1">Sign in to continue</p>
            </div>

            <form @submit.prevent="submit" class="card space-y-4">
                <div>
                    <label for="email" class="block text-sm font-medium text-brand-gray-light mb-1">Email</label>
                    <input id="email" v-model="form.email" type="email" autofocus
                        :class="form.errors.email ? 'input-field-error' : 'input-field'" class="w-full"
                        placeholder="admin@wjcafe.com" />
                    <p v-if="form.errors.email" class="mt-1 text-sm text-brand-red-light">{{ form.errors.email }}</p>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-brand-gray-light mb-1">Password</label>
                    <input id="password" v-model="form.password" type="password"
                        :class="form.errors.password ? 'input-field-error' : 'input-field'" class="w-full"
                        placeholder="••••••••" />
                    <p v-if="form.errors.password" class="mt-1 text-sm text-brand-red-light">{{ form.errors.password }}</p>
                </div>

                <label class="flex items-center gap-2 text-sm text-brand-gray-light">
                    <input type="checkbox" v-model="form.remember" class="rounded border-brand-input-border bg-brand-input-bg" />
                    Remember me
                </label>

                <button type="submit" class="btn-primary w-full" :disabled="form.processing">
                    {{ form.processing ? 'Signing in...' : 'Sign In' }}
                </button>
            </form>
        </div>
    </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';

// No layout for the login page
defineOptions({ layout: null });

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>
