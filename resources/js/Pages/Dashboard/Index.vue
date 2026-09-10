<script setup>
import { reactive, computed } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    isAdmin: { type: Boolean, default: false },
    today: {
        type: Object,
        default: () => ({ date: '', revenue: 0, orders: 0, cash: 0, topItems: [] }),
    },
    // Admin-only. Null for staff.
    itemPerformance: {
        type: Object,
        default: null,
    },
    filters: {
        type: Object,
        default: null,
    },
});

const range = reactive({
    from: props.filters?.from ?? '',
    to:   props.filters?.to   ?? '',
});

function money(v) {
    return Number(v ?? 0).toFixed(2);
}

function applyRange() {
    router.get('/dashboard', { from: range.from, to: range.to }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

// Quick range presets
function setToday() {
    const t = new Date().toISOString().split('T')[0];
    range.from = t; range.to = t; applyRange();
}
function setLast7() {
    const to = new Date();
    const from = new Date(); from.setDate(from.getDate() - 6);
    range.from = from.toISOString().split('T')[0];
    range.to = to.toISOString().split('T')[0];
    applyRange();
}
function setThisMonth() {
    const now = new Date();
    const from = new Date(now.getFullYear(), now.getMonth(), 1);
    range.from = from.toISOString().split('T')[0];
    range.to = now.toISOString().split('T')[0];
    applyRange();
}

const items = computed(() => props.itemPerformance?.items ?? []);
const maxQty = computed(() => items.value.reduce((m, i) => Math.max(m, i.quantity_sold), 0) || 1);
const rangeRevenue = computed(() => items.value.reduce((s, i) => s + Number(i.revenue), 0));
const rangeUnits = computed(() => items.value.reduce((s, i) => s + Number(i.quantity_sold), 0));
</script>

<template>
    <div class="min-h-screen bg-brand-black p-4 sm:p-6 lg:p-8">
        <!-- Header -->
        <header class="mb-6">
            <h1 class="text-2xl sm:text-3xl font-bold text-white">Dashboard</h1>
            <p class="mt-1 text-brand-gray-mid">{{ today.date }}</p>
        </header>

        <!-- Today at a glance -->
        <section class="mb-8">
            <h2 class="text-lg font-semibold text-white mb-3">Today at a Glance</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="card">
                    <p class="text-brand-gray-mid text-sm">Revenue Today</p>
                    <p class="text-3xl font-bold text-brand-red mt-1">₹{{ money(today.revenue) }}</p>
                </div>
                <div class="card">
                    <p class="text-brand-gray-mid text-sm">Orders Today</p>
                    <p class="text-3xl font-bold text-white mt-1">{{ today.orders }}</p>
                </div>
                <div class="card">
                    <p class="text-brand-gray-mid text-sm">Cash Collected Today</p>
                    <p class="text-3xl font-bold text-green-400 mt-1">₹{{ money(today.cash) }}</p>
                    <p class="text-brand-gray-mid text-xs mt-1">From bills marked paid today</p>
                </div>
            </div>
        </section>

        <!-- Today's top items -->
        <section v-if="today.topItems && today.topItems.length" class="mb-8">
            <h2 class="text-lg font-semibold text-white mb-3">Top Sellers Today</h2>
            <div class="card">
                <ul class="space-y-2">
                    <li v-for="(it, idx) in today.topItems" :key="idx"
                        class="flex items-center justify-between text-sm border-b border-brand-black-lighter/50 last:border-0 pb-2 last:pb-0">
                        <span class="text-white">
                            <span class="text-brand-red-accent font-bold mr-2">{{ idx + 1 }}.</span>{{ it.name }}
                        </span>
                        <span class="text-brand-gray-light">{{ it.quantity_sold }} sold · ₹{{ money(it.revenue) }}</span>
                    </li>
                </ul>
            </div>
        </section>

        <!-- Item performance (best-sellers over a range) — admin only -->
        <section v-if="isAdmin">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                <h2 class="text-lg font-semibold text-white">Item Performance</h2>
                <span class="text-brand-gray-mid text-sm">{{ itemPerformance.label }}</span>
            </div>

            <!-- Range controls -->
            <div class="card mb-4">
                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="block text-xs text-brand-gray-mid mb-1">From</label>
                        <input v-model="range.from" type="date" class="input-field" @change="applyRange" />
                    </div>
                    <div>
                        <label class="block text-xs text-brand-gray-mid mb-1">To</label>
                        <input v-model="range.to" type="date" class="input-field" @change="applyRange" />
                    </div>
                    <div class="flex gap-2">
                        <button type="button" class="btn-secondary text-sm" @click="setToday">Today</button>
                        <button type="button" class="btn-secondary text-sm" @click="setLast7">Last 7 days</button>
                        <button type="button" class="btn-secondary text-sm" @click="setThisMonth">This month</button>
                    </div>
                </div>
            </div>

            <!-- Range summary -->
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div class="card">
                    <p class="text-brand-gray-mid text-sm">Units Sold</p>
                    <p class="text-2xl font-bold text-white">{{ rangeUnits }}</p>
                </div>
                <div class="card">
                    <p class="text-brand-gray-mid text-sm">Revenue</p>
                    <p class="text-2xl font-bold text-brand-red">₹{{ money(rangeRevenue) }}</p>
                </div>
            </div>

            <!-- Item table -->
            <div class="card">
                <div v-if="items.length === 0" class="py-8 text-center">
                    <p class="text-brand-gray-mid">No sales in this period.</p>
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-brand-black-lighter">
                                <th class="text-left text-brand-gray-mid py-2 pr-4">#</th>
                                <th class="text-left text-brand-gray-mid py-2 px-2">Item</th>
                                <th class="text-left text-brand-gray-mid py-2 px-2 w-1/3">Volume</th>
                                <th class="text-right text-brand-gray-mid py-2 px-2">Qty</th>
                                <th class="text-right text-brand-gray-mid py-2 pl-2">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(it, idx) in items" :key="it.name"
                                class="border-b border-brand-black-lighter/50 last:border-0">
                                <td class="py-2 pr-4 text-brand-gray-mid">{{ idx + 1 }}</td>
                                <td class="py-2 px-2 text-white">{{ it.name }}</td>
                                <td class="py-2 px-2">
                                    <div class="h-2 rounded bg-brand-black-lighter overflow-hidden">
                                        <div class="h-full bg-brand-red"
                                             :style="{ width: ((it.quantity_sold / maxQty) * 100) + '%' }"></div>
                                    </div>
                                </td>
                                <td class="py-2 px-2 text-right text-brand-gray-light">{{ it.quantity_sold }}</td>
                                <td class="py-2 pl-2 text-right text-white font-medium">₹{{ money(it.revenue) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</template>
