<template>
  <div class="min-h-screen bg-brand-black">
    <!-- Navigation Bar -->
    <nav class="bg-brand-black border-b border-brand-black-lighter" aria-label="Main navigation">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
          <div class="flex-shrink-0">
            <Link href="/orders/tables" class="text-white font-bold text-lg hover:text-brand-red-accent transition-colors">
              WhiteJersey Cafe
            </Link>
          </div>

          <!-- Desktop nav -->
          <div class="hidden md:flex md:items-center md:space-x-1">
            <NavLink v-for="link in navLinks" :key="link.href" :href="link.href" :active="isActive(link.href)">
              {{ link.label }}
            </NavLink>
          </div>

          <!-- Mobile hamburger -->
          <div class="md:hidden">
            <button type="button"
              class="inline-flex items-center justify-center p-2 rounded-lg text-brand-gray-mid hover:text-white hover:bg-brand-black-light focus:outline-none focus:ring-2 focus:ring-brand-btn-focus-ring min-w-[44px] min-h-[44px]"
              :aria-expanded="mobileMenuOpen"
              @click="mobileMenuOpen = !mobileMenuOpen">
              <span class="sr-only">{{ mobileMenuOpen ? 'Close menu' : 'Open menu' }}</span>
              <svg v-if="!mobileMenuOpen" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
              </svg>
              <svg v-else class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>
      </div>

      <!-- Mobile menu -->
      <div v-show="mobileMenuOpen" class="md:hidden border-t border-brand-black-lighter">
        <div class="px-2 pt-2 pb-3 space-y-1">
          <MobileNavLink v-for="link in navLinks" :key="link.href" :href="link.href"
            :active="isActive(link.href)" @click="mobileMenuOpen = false">
            {{ link.label }}
          </MobileNavLink>
        </div>
      </div>
    </nav>

    <!-- Flash messages — shown after any navigation -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <Transition
        enter-active-class="transition-all duration-300"
        enter-from-class="opacity-0 -translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition-all duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="flash.success" class="mt-4 flex items-center gap-3 px-4 py-3 rounded-lg bg-green-700 text-white" role="alert">
          <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          <span class="font-medium">{{ flash.success }}</span>
        </div>
      </Transition>

      <Transition
        enter-active-class="transition-all duration-300"
        enter-from-class="opacity-0 -translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition-all duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="flash.error" class="mt-4 flex items-center gap-3 px-4 py-3 rounded-lg bg-red-600 text-white" role="alert">
          <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
          <span class="font-medium">{{ flash.error }}</span>
        </div>
      </Transition>
    </div>

    <!-- Page content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
      <slot />
    </main>
  </div>
</template>

<script setup>
import { ref, reactive, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import NavLink from '@/Components/NavLink.vue';
import MobileNavLink from '@/Components/MobileNavLink.vue';

const mobileMenuOpen = ref(false);
const page = usePage();

// Reactive flash — watch page props so it updates after every Inertia navigation
const flash = reactive({ success: null, error: null });

watch(
    () => page.props.flash,
    (newFlash) => {
        flash.success = newFlash?.success || null;
        flash.error   = newFlash?.error   || null;

        // Auto-dismiss after 4 seconds
        if (flash.success || flash.error) {
            setTimeout(() => {
                flash.success = null;
                flash.error   = null;
            }, 4000);
        }
    },
    { immediate: true, deep: true }
);

const navLinks = [
    { href: '/menu',               label: 'Menu' },
    { href: '/orders/tables',      label: 'Orders' },
    { href: '/inventory',          label: 'Inventory' },
    { href: '/reports/sales',      label: 'Sales' },
    { href: '/reports/profit-loss', label: 'P&L' },
];

function isActive(href) {
    const url = page.url;
    if (href === '/') return url === '/';
    return url.startsWith(href);
}
</script>
