<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    dailySpending: {
        type: Object,
        default: () => ({
            date: new Date().toISOString().split('T')[0],
            entries: [],
            totalCost: 0,
        }),
    },
    monthlySpending: {
        type: Object,
        default: null,
    },
});

// --- Tab management ---
const activeTab = ref('daily');

// --- Purchase Entry Form ---
const today = new Date().toISOString().split('T')[0];

const form = useForm({
    item_name: '',
    quantity: '',
    cost: '',
    purchase_date: today,
});

function submitPurchase() {
    form.post('/inventory', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

// --- Computed helpers ---
const dailyEntries = computed(() => props.dailySpending?.entries ?? []);
const dailyTotal = computed(() => Number(props.dailySpending?.totalCost ?? 0).toFixed(2));
const dailyDate = computed(() => props.dailySpending?.date ?? today);

const monthlyItems = computed(() => props.monthlySpending?.itemTotals ?? []);
const monthlyGrandTotal = computed(() => Number(props.monthlySpending?.grandTotal ?? 0).toFixed(2));
const monthlyLabel = computed(() => {
    if (!props.monthlySpending) return '';
    const date = new Date(props.monthlySpending.year, props.monthlySpending.month - 1);
    return date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
});

// --- Navigation helpers ---
function viewMonthly() {
    activeTab.value = 'monthly';
    if (!props.monthlySpending) {
        const now = new Date();
        router.get('/inventory', {
            view: 'monthly',
            year: now.getFullYear(),
            month: now.getMonth() + 1,
        }, { preserveState: true, preserveScroll: true });
    }
}

function viewDaily() {
    activeTab.value = 'daily';
}
</script>

<template>
    <div class="min-h-screen bg-brand-black p-4 sm:p-6 lg:p-8">
        <!-- Header -->
        <header class="mb-8">
            <h1 class="text-2xl sm:text-3xl font-bold text-white">Inventory Purchases</h1>
            <p class="mt-1 text-brand-gray-mid">Record and track daily purchase spending</p>
        </header>

        <!-- Purchase Entry Form -->
        <section class="card mb-8" aria-labelledby="purchase-form-heading">
            <h2 id="purchase-form-heading" class="text-xl font-semibold text-white mb-4">
                Record Purchase
            </h2>

            <form @submit.prevent="submitPurchase" class="space-y-4">
                <!-- Item Name -->
                <div>
                    <label for="purchase-item-name" class="block text-sm font-medium text-brand-gray-light mb-1">
                        Item Name
                    </label>
                    <input
                        id="purchase-item-name"
                        v-model="form.item_name"
                        type="text"
                        :class="form.errors.item_name ? 'input-field-error' : 'input-field'"
                        class="w-full"
                        placeholder="e.g. Milk, Sugar, Eggs"
                        maxlength="100"
                    />
                    <p v-if="form.errors.item_name" class="mt-1 text-sm text-brand-red-light" role="alert">
                        {{ form.errors.item_name }}
                    </p>
                </div>

                <!-- Quantity and Cost row -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Quantity -->
                    <div>
                        <label for="purchase-quantity" class="block text-sm font-medium text-brand-gray-light mb-1">
                            Quantity
                        </label>
                        <input
                            id="purchase-quantity"
                            v-model="form.quantity"
                            type="number"
                            step="0.001"
                            min="0.001"
                            :class="form.errors.quantity ? 'input-field-error' : 'input-field'"
                            class="w-full"
                            placeholder="0"
                        />
                        <p v-if="form.errors.quantity" class="mt-1 text-sm text-brand-red-light" role="alert">
                            {{ form.errors.quantity }}
                        </p>
                    </div>

                    <!-- Cost -->
                    <div>
                        <label for="purchase-cost" class="block text-sm font-medium text-brand-gray-light mb-1">
                            Cost (₹)
                        </label>
                        <input
                            id="purchase-cost"
                            v-model="form.cost"
                            type="number"
                            step="0.01"
                            min="0.01"
                            max="999999.99"
                            :class="form.errors.cost ? 'input-field-error' : 'input-field'"
                            class="w-full"
                            placeholder="0.00"
                        />
                        <p v-if="form.errors.cost" class="mt-1 text-sm text-brand-red-light" role="alert">
                            {{ form.errors.cost }}
                        </p>
                    </div>
                </div>

                <!-- Purchase Date -->
                <div>
                    <label for="purchase-date" class="block text-sm font-medium text-brand-gray-light mb-1">
                        Purchase Date
                    </label>
                    <input
                        id="purchase-date"
                        v-model="form.purchase_date"
                        type="date"
                        :max="today"
                        :class="form.errors.purchase_date ? 'input-field-error' : 'input-field'"
                        class="w-full"
                    />
                    <p v-if="form.errors.purchase_date" class="mt-1 text-sm text-brand-red-light" role="alert">
                        {{ form.errors.purchase_date }}
                    </p>
                </div>

                <!-- Submit Button -->
                <div class="pt-2">
                    <button
                        type="submit"
                        class="btn-primary"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Saving...' : 'Record Purchase' }}
                    </button>
                </div>
            </form>
        </section>

        <!-- Tab Navigation -->
        <nav class="flex gap-2 mb-6" aria-label="Spending view tabs">
            <button
                type="button"
                :class="activeTab === 'daily' ? 'btn-primary' : 'btn-secondary'"
                @click="viewDaily"
            >
                Daily Spending
            </button>
            <button
                type="button"
                :class="activeTab === 'monthly' ? 'btn-primary' : 'btn-secondary'"
                @click="viewMonthly"
            >
                Monthly Summary
            </button>
        </nav>

        <!-- Daily Spending Section -->
        <section v-if="activeTab === 'daily'" aria-labelledby="daily-spending-heading">
            <div class="card">
                <h2 id="daily-spending-heading" class="text-xl font-semibold text-white mb-4">
                    Daily Spending — {{ dailyDate }}
                </h2>

                <div v-if="dailyEntries.length === 0" class="text-center py-6">
                    <p class="text-brand-gray-mid">No purchases recorded for this date.</p>
                </div>

                <div v-else>
                    <!-- Responsive table wrapper -->
                    <div class="overflow-x-auto -mx-6 px-6">
                        <table class="w-full text-sm" aria-label="Daily spending entries">
                            <thead>
                                <tr class="border-b border-brand-black-lighter">
                                    <th class="text-left text-brand-gray-mid py-3 pr-4 font-medium">Item</th>
                                    <th class="text-right text-brand-gray-mid py-3 px-3 font-medium">Qty</th>
                                    <th class="text-right text-brand-gray-mid py-3 pl-4 font-medium">Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(entry, index) in dailyEntries"
                                    :key="index"
                                    class="border-b border-brand-black-lighter/50 last:border-0"
                                >
                                    <td class="py-3 pr-4 text-white">{{ entry.item_name }}</td>
                                    <td class="py-3 px-3 text-right text-brand-gray-light">{{ entry.quantity }}</td>
                                    <td class="py-3 pl-4 text-right text-brand-gray-light">₹{{ Number(entry.cost).toFixed(2) }}</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="border-t-2 border-brand-red">
                                    <td class="py-3 pr-4 text-white font-semibold" colspan="2">Total</td>
                                    <td class="py-3 pl-4 text-right text-brand-red-accent font-bold text-base">
                                        ₹{{ dailyTotal }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- Monthly Spending Section -->
        <section v-if="activeTab === 'monthly'" aria-labelledby="monthly-spending-heading">
            <div class="card">
                <h2 id="monthly-spending-heading" class="text-xl font-semibold text-white mb-4">
                    Monthly Summary{{ monthlyLabel ? ' — ' + monthlyLabel : '' }}
                </h2>

                <div v-if="!monthlySpending || monthlyItems.length === 0" class="text-center py-6">
                    <p class="text-brand-gray-mid">No spending data for this month.</p>
                </div>

                <div v-else>
                    <div class="overflow-x-auto -mx-6 px-6">
                        <table class="w-full text-sm" aria-label="Monthly spending summary by item">
                            <thead>
                                <tr class="border-b border-brand-black-lighter">
                                    <th class="text-left text-brand-gray-mid py-3 pr-4 font-medium">Item</th>
                                    <th class="text-right text-brand-gray-mid py-3 pl-4 font-medium">Total Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(item, index) in monthlyItems"
                                    :key="index"
                                    class="border-b border-brand-black-lighter/50 last:border-0"
                                >
                                    <td class="py-3 pr-4 text-white">{{ item.item_name }}</td>
                                    <td class="py-3 pl-4 text-right text-brand-gray-light">₹{{ Number(item.total_cost).toFixed(2) }}</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="border-t-2 border-brand-red">
                                    <td class="py-3 pr-4 text-white font-semibold">Grand Total</td>
                                    <td class="py-3 pl-4 text-right text-brand-red-accent font-bold text-base">
                                        ₹{{ monthlyGrandTotal }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
