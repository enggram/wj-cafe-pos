<template>
    <div class="min-h-screen bg-brand-black p-4 sm:p-6 lg:p-8">
        <header class="mb-8">
            <h1 class="text-2xl sm:text-3xl font-bold text-white">Database Backup</h1>
            <p class="mt-1 text-brand-gray-mid">Download a backup of all your data, or restore from a previous backup</p>
        </header>

        <!-- DB info -->
        <section class="card mb-6">
            <h2 class="text-lg font-semibold text-white mb-3">Current Database</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                <div>
                    <dt class="text-brand-gray-mid">Type</dt>
                    <dd class="text-white font-medium uppercase">{{ dbInfo.driver }}</dd>
                </div>
                <div>
                    <dt class="text-brand-gray-mid">Size</dt>
                    <dd class="text-white font-medium">{{ dbInfo.size }}</dd>
                </div>
                <div class="sm:col-span-1">
                    <dt class="text-brand-gray-mid">Status</dt>
                    <dd class="text-green-400 font-medium">● Active</dd>
                </div>
            </dl>
        </section>

        <!-- Download -->
        <section class="card mb-6">
            <h2 class="text-lg font-semibold text-white mb-1">Download Backup</h2>
            <p class="text-brand-gray-mid text-sm mb-4">
                Save a copy of your entire database to your computer. Do this after adding menu items or at end of day.
            </p>
            <div class="flex flex-wrap gap-3">
                <a href="/backup/download/sqlite"
                   class="btn-primary inline-flex items-center gap-2">
                    ⬇ Download Database File (.sqlite)
                </a>
                <a href="/backup/download/sql"
                   class="btn-secondary inline-flex items-center gap-2">
                    ⬇ Download SQL Dump (.sql)
                </a>
            </div>
            <p class="text-brand-gray-mid text-xs mt-3">
                The <b>.sqlite</b> file is the exact database (use it to restore below).
                The <b>.sql</b> file is a readable text export you can open or import elsewhere.
            </p>
        </section>

        <!-- Restore -->
        <section class="card border-brand-red/30">
            <h2 class="text-lg font-semibold text-white mb-1">Restore from Backup</h2>
            <p class="text-brand-gray-mid text-sm mb-4">
                Upload a previously downloaded <b>.sqlite</b> file to replace the current database.
            </p>

            <div class="mb-4 p-3 rounded-lg bg-brand-red/10 border border-brand-red/40">
                <p class="text-brand-red-light text-sm">
                    ⚠ Warning: Restoring <b>replaces all current data</b> with the backup.
                    A safety copy of the current database is kept automatically.
                </p>
            </div>

            <form @submit.prevent="submitRestore" class="space-y-3">
                <input
                    ref="fileInput"
                    type="file"
                    accept=".sqlite,.db"
                    @change="onFileChange"
                    class="block w-full text-sm text-brand-gray-light
                           file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0
                           file:bg-brand-black-lighter file:text-white file:cursor-pointer
                           hover:file:bg-brand-black-light"
                />
                <p v-if="form.errors.backup" class="text-brand-red-light text-sm">{{ form.errors.backup }}</p>

                <button type="submit"
                    class="btn-secondary text-brand-red-light border-brand-red hover:bg-brand-red hover:text-white"
                    :disabled="!form.backup || form.processing">
                    {{ form.processing ? 'Restoring...' : 'Restore Database' }}
                </button>
            </form>
        </section>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

defineProps({
    dbInfo: {
        type: Object,
        default: () => ({ driver: 'sqlite', path: '', size: '0 KB' }),
    },
});

const fileInput = ref(null);
const form = useForm({ backup: null });

function onFileChange(e) {
    form.backup = e.target.files[0] || null;
}

function submitRestore() {
    if (!confirm('This will replace ALL current data with the backup. Continue?')) return;
    form.post('/backup/restore', {
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            if (fileInput.value) fileInput.value.value = '';
        },
    });
}
</script>
