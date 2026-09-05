<template>
  <Link
    :href="href"
    class="px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150 min-h-[44px] inline-flex items-center"
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
