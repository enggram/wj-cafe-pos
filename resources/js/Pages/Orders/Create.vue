<template>
    <div class="min-h-screen bg-brand-black p-4 sm:p-6">

        <!-- Inline notification -->
        <div v-if="notification" :class="notification.type === 'success' ? 'bg-green-700' : 'bg-red-600'"
             class="mb-4 flex items-center gap-3 px-4 py-3 rounded-lg text-white">
            <span>{{ notification.type === 'success' ? '✓' : '✕' }}</span>
            <span class="font-medium">{{ notification.message }}</span>
        </div>

        <!-- Header -->
        <header class="mb-6 flex items-center justify-between flex-wrap gap-2">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-white">
                    Table <span class="text-brand-red">{{ table.table_number }}</span>
                </h1>
                <p class="text-brand-gray-mid mt-1">
                    {{ existingOrder ? 'Active order — add items or generate bill' : 'Create a new order' }}
                </p>
            </div>
            <Link href="/orders/tables" class="btn-secondary text-sm">← Tables</Link>
        </header>

        <!-- Current Order Items -->
        <section v-if="existingOrder && existingOrder.order_items && existingOrder.order_items.length > 0" class="mb-6">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-lg font-semibold text-white">Current Order</h2>
                <span class="text-brand-gray-mid text-sm">
                    Total: <span class="text-brand-red-accent font-bold text-base">₹{{ orderTotal }}</span>
                </span>
            </div>

            <div class="card overflow-x-auto mb-4">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-brand-black-lighter">
                            <th class="text-left text-brand-gray-mid py-2 pr-4">Item</th>
                            <th class="text-center text-brand-gray-mid py-2 px-2">Qty</th>
                            <th class="text-right text-brand-gray-mid py-2 pl-4">Price</th>
                            <th class="text-right text-brand-gray-mid py-2 pl-4">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in existingOrder.order_items" :key="item.id"
                            class="border-b border-brand-black-lighter/50 last:border-0">
                            <td class="py-2 pr-4 text-white">
                                {{ item.menu_item?.name ?? `Item #${item.menu_item_id}` }}
                                <span v-if="item.is_parcel" class="ml-2 text-xs px-1.5 py-0.5 rounded bg-brand-red/20 text-brand-red-accent">Parcel</span>
                            </td>
                            <td class="py-2 px-2 text-center text-brand-gray-light">{{ item.quantity }}</td>
                            <td class="py-2 pl-4 text-right text-brand-gray-light">₹{{ Number(item.unit_price).toFixed(2) }}</td>
                            <td class="py-2 pl-4 text-right text-white font-medium">
                                ₹{{ (Number(item.unit_price) * item.quantity).toFixed(2) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Generate Bill -->
            <button type="button"
                class="w-full py-3 rounded-lg bg-green-700 hover:bg-green-600 active:bg-green-800 text-white font-bold text-lg transition-colors disabled:opacity-50"
                :disabled="billForm.processing"
                @click="generateBill">
                {{ billForm.processing ? '⏳ Generating bill...' : '🧾 Generate Bill & Proceed to Payment' }}
            </button>
        </section>

        <!-- Select Items -->
        <section class="mb-24">
            <h2 class="text-lg font-semibold text-white mb-3">
                {{ existingOrder ? 'Add More Items' : 'Select Items' }}
            </h2>

            <div v-if="menuItems.length === 0" class="card">
                <p class="text-brand-gray-mid">No menu items available. Add items in Menu Management first.</p>
            </div>

            <div class="space-y-2">
                <div v-for="item in menuItems" :key="item.id"
                     class="card !p-3 sm:!p-4">
                    <!-- Dine-in row -->
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <p class="text-white font-medium truncate">{{ item.name }}</p>
                            <p class="text-brand-gray-mid text-sm">
                                <span class="text-brand-red-accent">₹{{ Number(item.price).toFixed(2) }}</span>
                                <span v-if="item.category" class="ml-2">· {{ item.category.name }}</span>
                                <span v-if="Number(item.parcel_rate) > 0" class="ml-2 text-brand-gray-mid">· Parcel ₹{{ Number(item.parcel_rate).toFixed(2) }}/unit</span>
                            </p>
                        </div>
                        <div class="flex items-center gap-1 sm:gap-2 shrink-0">
                            <button type="button"
                                class="btn-secondary !px-0 flex items-center justify-center w-[44px] h-[44px] text-lg font-bold"
                                :disabled="getQuantity(item.id, false) <= 0"
                                @click="decrementQuantity(item.id, false)">−</button>
                            <span class="w-8 text-center text-white font-semibold tabular-nums">{{ getQuantity(item.id, false) }}</span>
                            <button type="button"
                                class="btn-primary !px-0 flex items-center justify-center w-[44px] h-[44px] text-lg font-bold"
                                :disabled="getQuantity(item.id, false) >= 99"
                                @click="incrementQuantity(item.id, false)">+</button>
                        </div>
                    </div>

                    <!-- Parcel toggle (only when item has a parcel rate) -->
                    <div v-if="Number(item.parcel_rate) > 0" class="mt-2 pt-2 border-t border-brand-black-lighter">
                        <button type="button"
                            class="btn-secondary text-xs px-3 py-1 min-h-[32px]"
                            :class="parcelExpanded[item.id] ? 'text-brand-red-accent border-brand-red' : ''"
                            @click="toggleParcel(item.id)">
                            {{ parcelExpanded[item.id] ? '✕ Hide Parcel' : '+ Parcel' }}
                        </button>

                        <!-- Parcel sub-row -->
                        <div v-if="parcelExpanded[item.id]" class="mt-2 flex items-center justify-between gap-3 pl-2">
                            <div class="flex-1 min-w-0">
                                <p class="text-brand-gray-light text-sm font-medium">Parcel</p>
                                <p class="text-brand-gray-mid text-xs">
                                    ₹{{ (Number(item.parcel_rate) * getQuantity(item.id, true)).toFixed(2) }} parcel
                                </p>
                            </div>
                            <div class="flex items-center gap-1 sm:gap-2 shrink-0">
                                <button type="button"
                                    class="btn-secondary !px-0 flex items-center justify-center w-[44px] h-[44px] text-lg font-bold"
                                    :disabled="getQuantity(item.id, true) <= 0"
                                    @click="decrementQuantity(item.id, true)">−</button>
                                <span class="w-8 text-center text-white font-semibold tabular-nums">{{ getQuantity(item.id, true) }}</span>
                                <button type="button"
                                    class="btn-primary !px-0 flex items-center justify-center w-[44px] h-[44px] text-lg font-bold"
                                    :disabled="getQuantity(item.id, true) >= 99"
                                    @click="incrementQuantity(item.id, true)">+</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Sticky submit bar -->
        <div class="fixed bottom-0 left-0 right-0 bg-brand-black border-t border-brand-black-lighter px-4 py-4 sm:px-6">
            <div class="max-w-7xl mx-auto flex items-center justify-between gap-4">
                <div>
                    <p class="text-white font-semibold">{{ selectedItemsCount }} item{{ selectedItemsCount !== 1 ? 's' : '' }} selected</p>
                    <p v-if="form.errors.items" class="text-brand-red-light text-xs mt-0.5">{{ form.errors.items }}</p>
                </div>
                <button type="button" class="btn-primary px-8"
                    :disabled="form.processing || selectedItemsCount === 0"
                    @click="submitOrder">
                    {{ form.processing ? '⏳ Saving...' : (existingOrder ? '➕ Add Items' : '✓ Create Order') }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    table:         { type: Object, required: true },
    menuItems:     { type: Array,  required: true },
    existingOrder: { type: Object, default: null },
});

// ── Inline notification ──────────────────────────────────────
const notification = ref(null);

function showNotification(type, message) {
    notification.value = { type, message };
    setTimeout(() => { notification.value = null; }, 4000);
}

// ── Quantity helpers (keyed by composite menu_item_id:is_parcel) ─
const quantities = reactive({});

// Per-item UI state: whether the parcel sub-row is expanded
const parcelExpanded = ref({});

function lineKey(itemId, isParcel) { return `${itemId}:${isParcel ? 1 : 0}`; }

function getQuantity(itemId, isParcel = false) {
    return quantities[lineKey(itemId, isParcel)] || 0;
}

function incrementQuantity(itemId, isParcel = false) {
    const key = lineKey(itemId, isParcel);
    const current = quantities[key] || 0;
    if (current < 99) quantities[key] = current + 1;
}

function decrementQuantity(itemId, isParcel = false) {
    const key = lineKey(itemId, isParcel);
    const current = quantities[key] || 0;
    quantities[key] = current > 1 ? current - 1 : 0;
}

function toggleParcel(itemId) {
    parcelExpanded.value[itemId] = !parcelExpanded.value[itemId];
}

const selectedItemsCount = computed(() =>
    Object.values(quantities).filter(q => q > 0).length
);

const orderTotal = computed(() => {
    if (!props.existingOrder?.order_items) return '0.00';
    return props.existingOrder.order_items
        .reduce((sum, item) => sum + Number(item.unit_price) * item.quantity, 0)
        .toFixed(2);
});

// ── Order submission ─────────────────────────────────────────
const form = useForm({ table_id: props.table.id, items: [] });

function submitOrder() {
    const items = Object.entries(quantities)
        .filter(([, qty]) => qty > 0)
        .map(([key, qty]) => {
            const [itemId, parcelFlag] = key.split(':');
            return {
                menu_item_id:   parseInt(itemId),
                quantity:       qty,
                sub_variety_id: null,
                is_parcel:      parcelFlag === '1',
            };
        });

    form.table_id = props.table.id;
    form.items    = items;

    // Reset local quantities before submit so the redirected page shows fresh state
    const resetQuantities = () => Object.keys(quantities).forEach(k => { quantities[k] = 0; });

    if (props.existingOrder) {
        form.post(`/orders/${props.existingOrder.id}/items`, {
            onFinish: resetQuantities,
            onError: () => showNotification('error', 'Failed to add items. Please try again.'),
        });
    } else {
        form.post('/orders', {
            onFinish: resetQuantities,
            onError: () => showNotification('error', 'Failed to create order. Please try again.'),
        });
    }
}

// ── Bill generation ──────────────────────────────────────────
const billForm = useForm({});

function generateBill() {
    billForm.post(`/billing/generate/${props.table.id}`, {
        onError: () => showNotification('error', 'Could not generate bill. Make sure there are items in the order.'),
    });
    // On success Inertia follows the redirect to /billing/{table} automatically
}
</script>
