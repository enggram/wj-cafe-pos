<template>
  <Link
    :href="href"
    class="block px-3 py-3 rounded-lg text-base font-medium transition-colors duration-150 min-h-[44px]"
    :class="active
      ? 'bg-brand-black-light text-brand-red-accent border border-brand-red'
      : 'text-brand-gray-mid hover:text-white hover:bg-brand-black-light'"
    :aria-current="active ? 'page' : undefined"
  >
    <slot />
  </Link>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
  href: {
    type: String,
    required: true,
  },
  active: {
    type: Boolean,
    default: false,
  },
});
</script>
