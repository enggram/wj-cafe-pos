<template>
  <div class="min-h-screen bg-brand-black">
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
            <NavLink v-for="link in visibleLinks" :key="link.href" :href="link.href" :active="isActive(link.href)">
              {{ link.label }}
            </NavLink>
            <div v-if="user" class="ml-4 flex items-center gap-3 pl-4 border-l border-brand-black-lighter">
              <span class="text-sm text-brand-gray-light">
                {{ user.name }}
                <span class="text-xs px-2 py-0.5 rounded-full ml-1"
                  :class="user.isAdmin ? 'bg-brand-red text-white' : 'bg-brand-black-lighter text-brand-gray-mid'">
                  {{ user.role }}
                </span>
              </span>
              <button type="button" class="btn-secondary text-sm !py-1.5" @click="logout">Logout</button>
            </div>
          </div>

          <!-- Mobile hamburger -->
          <div class="md:hidden">
            <button type="button"
              class="inline-flex items-center justify-center p-2 rounded-lg text-brand-gray-mid hover:text-white hover:bg-brand-black-light focus:outline-none focus:ring-2 focus:ring-brand-btn-focus-ring min-w-[44px] min-h-[44px]"
              @click="mobileMenuOpen = !mobileMenuOpen">
              <span class="sr-only">Menu</span>
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
          <MobileNavLink v-for="link in visibleLinks" :key="link.href" :href="link.href"
            :active="isActive(link.href)" @click="mobileMenuOpen = false">
            {{ link.label }}
          </MobileNavLink>
          <div v-if="user" class="px-3 py-3 border-t border-brand-black-lighter mt-2">
            <p class="text-sm text-brand-gray-light mb-2">{{ user.name }} ({{ user.role }})</p>
            <button type="button" class="btn-secondary text-sm w-full" @click="logout">Logout</button>
          </div>
        </div>
      </div>
    </nav>

    <!-- Flash messages -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <Transition enter-active-class="transition-all duration-300" enter-from-class="opacity-0 -translate-y-2"
        enter-to-class="opacity-100 translate-y-0" leave-active-class="transition-all duration-200"
        leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div v-if="flash.success" class="mt-4 flex items-center gap-3 px-4 py-3 rounded-lg bg-green-700 text-white" role="alert">
          <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          <span class="font-medium">{{ flash.success }}</span>
        </div>
      </Transition>
      <Transition enter-active-class="transition-all duration-300" enter-from-class="opacity-0 -translate-y-2"
        enter-to-class="opacity-100 translate-y-0" leave-active-class="transition-all duration-200"
        leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div v-if="flash.error" class="mt-4 flex items-center gap-3 px-4 py-3 rounded-lg bg-red-600 text-white" role="alert">
          <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
          <span class="font-medium">{{ flash.error }}</span>
        </div>
      </Transition>
    </div>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
      <slot />
    </main>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import NavLink from '@/Components/NavLink.vue';
import MobileNavLink from '@/Components/MobileNavLink.vue';

const mobileMenuOpen = ref(false);
const page = usePage();

const user = computed(() => page.props.auth?.user || null);

// Nav links with role requirement. adminOnly links hidden from staff.
const allLinks = [
  { href: '/menu',                label: 'Menu',      adminOnly: true },
  { href: '/orders/tables',       label: 'Orders',    adminOnly: false },
  { href: '/inventory',           label: 'Inventory', adminOnly: true },
  { href: '/reports/sales',       label: 'Sales',     adminOnly: true },
  { href: '/reports/profit-loss', label: 'P&L',       adminOnly: true },
  { href: '/users',               label: 'Users',     adminOnly: true },
];

const visibleLinks = computed(() => {
  const isAdmin = user.value?.isAdmin;
  return allLinks.filter(l => !l.adminOnly || isAdmin);
});

const flash = reactive({ success: null, error: null });
watch(
  () => page.props.flash,
  (f) => {
    flash.success = f?.success || null;
    flash.error = f?.error || null;
    if (flash.success || flash.error) {
      setTimeout(() => { flash.success = null; flash.error = null; }, 4000);
    }
  },
  { immediate: true, deep: true }
);

function isActive(href) {
  const url = page.url;
  if (href === '/') return url === '/';
  return url.startsWith(href);
}

function logout() {
  router.post('/logout');
}
</script>
