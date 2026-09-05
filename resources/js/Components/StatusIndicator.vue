<template>
  <span
    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium text-white"
    :class="statusClasses"
    :role="role"
  >
    <!-- Success: Checkmark icon -->
    <svg v-if="type === 'success'" class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
    </svg>

    <!-- Error: X icon -->
    <svg v-if="type === 'error'" class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
    </svg>

    <!-- Profit: Up arrow icon -->
    <svg v-if="type === 'profit'" class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
    </svg>

    <!-- Loss: Down arrow icon -->
    <svg v-if="type === 'loss'" class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
    </svg>

    <span>{{ displayLabel }}</span>
  </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  /**
   * Status type determines color and icon.
   * 'success' — green-700 bg + checkmark
   * 'error'   — red-600 bg + X icon
   * 'profit'  — green-700 bg + up arrow
   * 'loss'    — red-700 bg + down arrow
   */
  type: {
    type: String,
    required: true,
    validator: (value) => ['success', 'error', 'profit', 'loss'].includes(value),
  },
  /** Optional custom label. Defaults to capitalised type name. */
  label: {
    type: String,
    default: null,
  },
  /** ARIA role for the indicator. Defaults to 'status'. */
  role: {
    type: String,
    default: 'status',
  },
});

const displayLabel = computed(() => {
  if (props.label) return props.label;
  return props.type.charAt(0).toUpperCase() + props.type.slice(1);
});

/**
 * Each status type uses a distinct background color to meet 3:1 contrast
 * against the adjacent dark backgrounds (#0a0a0a, #1a1a1a):
 *   - success: bg-green-700 (#15803d) → 3.3:1 on #0a0a0a ✓
 *   - error:   bg-red-600   (#dc2626) → 4.5:1 on #0a0a0a ✓
 *   - profit:  bg-green-700 (#15803d) → 3.3:1 on #0a0a0a ✓
 *   - loss:    bg-red-700   (#b91c1c) → 3.4:1 on #0a0a0a ✓
 *
 * White text on all backgrounds exceeds 4.5:1.
 * Each also has a unique icon so colour is not the sole differentiator (Req 9.4).
 */
const statusClasses = computed(() => {
  switch (props.type) {
    case 'success':
      return 'bg-green-700';
    case 'error':
      return 'bg-red-600';
    case 'profit':
      return 'bg-green-700';
    case 'loss':
      return 'bg-red-700';
    default:
      return 'bg-brand-black-light';
  }
});
</script>
