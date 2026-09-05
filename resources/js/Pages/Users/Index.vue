<template>
    <div class="min-h-screen bg-brand-black p-4 sm:p-6 lg:p-8">
        <header class="mb-8">
            <h1 class="text-2xl sm:text-3xl font-bold text-white">User Management</h1>
            <p class="mt-1 text-brand-gray-mid">Create and manage staff and admin accounts</p>
        </header>

        <!-- Add / Edit form -->
        <section class="card mb-8">
            <h2 class="text-xl font-semibold text-white mb-4">{{ editing ? 'Edit User' : 'Add New User' }}</h2>
            <form @submit.prevent="submit" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-brand-gray-light mb-1">Name</label>
                        <input v-model="form.name" type="text"
                            :class="form.errors.name ? 'input-field-error' : 'input-field'" class="w-full" />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-brand-red-light">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-brand-gray-light mb-1">Email</label>
                        <input v-model="form.email" type="email"
                            :class="form.errors.email ? 'input-field-error' : 'input-field'" class="w-full" />
                        <p v-if="form.errors.email" class="mt-1 text-sm text-brand-red-light">{{ form.errors.email }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-brand-gray-light mb-1">Role</label>
                        <select v-model="form.role" class="input-field w-full">
                            <option value="staff">Staff</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-brand-gray-light mb-1">
                            Password {{ editing ? '(leave blank to keep)' : '' }}
                        </label>
                        <input v-model="form.password" type="password"
                            :class="form.errors.password ? 'input-field-error' : 'input-field'" class="w-full" />
                        <p v-if="form.errors.password" class="mt-1 text-sm text-brand-red-light">{{ form.errors.password }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-brand-gray-light mb-1">Confirm Password</label>
                        <input v-model="form.password_confirmation" type="password" class="input-field w-full" />
                    </div>
                </div>
                <div class="flex gap-3">
                    <button type="submit" class="btn-primary" :disabled="form.processing">
                        {{ form.processing ? 'Saving...' : (editing ? 'Update User' : 'Add User') }}
                    </button>
                    <button v-if="editing" type="button" class="btn-secondary" @click="cancelEdit">Cancel</button>
                </div>
            </form>
        </section>

        <!-- Users list -->
        <section>
            <h2 class="text-xl font-semibold text-white mb-4">All Users</h2>
            <div class="space-y-2">
                <div v-for="u in users" :key="u.id"
                    class="card flex flex-wrap items-center justify-between gap-3 !p-4">
                    <div>
                        <p class="text-white font-medium">{{ u.name }}
                            <span class="ml-2 text-xs px-2 py-0.5 rounded-full"
                                :class="u.role === 'admin' ? 'bg-brand-red text-white' : 'bg-brand-black-lighter text-brand-gray-light'">
                                {{ u.role }}
                            </span>
                        </p>
                        <p class="text-brand-gray-mid text-sm">{{ u.email }}</p>
                    </div>
                    <div class="flex gap-2">
                        <button class="btn-secondary text-sm" @click="startEdit(u)">Edit</button>
                        <button v-if="u.id !== currentUserId"
                            class="btn-secondary text-sm text-brand-red-light border-brand-red hover:bg-brand-red hover:text-white"
                            @click="destroy(u)">Delete</button>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    users: { type: Array, default: () => [] },
});

const currentUserId = usePage().props.auth?.user?.id;
const editing = ref(null);

const form = useForm({
    name: '', email: '', role: 'staff', password: '', password_confirmation: '',
});

function startEdit(u) {
    editing.value = u;
    form.name = u.name;
    form.email = u.email;
    form.role = u.role;
    form.password = '';
    form.password_confirmation = '';
    form.clearErrors();
}

function cancelEdit() {
    editing.value = null;
    form.reset();
    form.clearErrors();
}

function submit() {
    if (editing.value) {
        form.put(`/users/${editing.value.id}`, { onSuccess: () => cancelEdit() });
    } else {
        form.post('/users', { onSuccess: () => form.reset() });
    }
}

function destroy(u) {
    if (confirm(`Delete user "${u.name}"?`)) {
        router.delete(`/users/${u.id}`);
    }
}
</script>
