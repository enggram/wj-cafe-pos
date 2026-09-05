<template>
    <Transition
        enter-active-class="transition-transform duration-300 ease-out"
        enter-from-class="-translate-y-full"
        enter-to-class="translate-y-0"
        leave-active-class="transition-transform duration-200 ease-in"
        leave-from-class="translate-y-0"
        leave-to-class="-translate-y-full"
    >
        <div
            v-if="isOffline"
            class="fixed top-0 left-0 right-0 z-50 flex items-center justify-center gap-2 px-4 py-2 bg-red-700 text-white text-sm font-medium shadow-lg"
            role="alert"
            aria-live="assertive"
        >
            <svg
                class="w-5 h-5 flex-shrink-0"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 9v2m0 4h.01M18.364 5.636a9 9 0 11-12.728 0M15.536 8.464a5 5 0 00-7.072 0"
                />
            </svg>
            <span>You are offline. Orders will be saved locally and synced when connection restores.</span>
        </div>
    </Transition>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const isOffline = ref(!navigator.onLine);

function handleOnline() {
    isOffline.value = false;
}

function handleOffline() {
    isOffline.value = true;
}

onMounted(() => {
    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);
});

onUnmounted(() => {
    window.removeEventListener('online', handleOnline);
    window.removeEventListener('offline', handleOffline);
});
</script>
