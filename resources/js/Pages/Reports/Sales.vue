<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    report: {
        type: Object,
        default: () => ({
            totalRevenue: 0,
            totalOrders: 0,
            itemSales: [],
            topItems: [],
            periodLabel: '',
        }),
    },
    filters: {
        type: Object,
        default: () => ({
            period: 'daily',
            date: new Date().toISOString().split('T')[0],
            year: new Date().getFullYear(),
            month: new Date().getMonth() + 1,
            start_date: null,
        }),
    },
});

// --- Period state ---
const activePeriod = ref(props.filters.period || 'daily');
const selectedDate = ref(props.filters.date || new Date().toISOString().split('T')[0]);
const selectedYear = ref(props.filters.year || new Date().getFullYear());
const selectedMonth = ref(props.filters.month || new Date().getMonth() + 1);
const selectedStartDate = ref(props.filters.start_date || new Date().toISOString().split('T')[0]);

const periods = [
    { key: 'daily', label: 'Daily' },
    { key: 'weekly', label: 'Weekly' },
    { key: 'monthly', label: 'Monthly' },
    { key: 'yearly', label: 'Yearly' },
];

// --- Computed helpers ---
const totalRevenue = computed(() => Number(props.report?.totalRevenue ?? 0).toFixed(2));
const totalOrders = computed(() => props.report?.totalOrders ?? 0);
const itemSales = computed(() => props.report?.itemSales ?? []);
const topItems = computed(() => props.report?.topItems ?? []);
const periodLabel = computed(() => props.report?.periodLabel ?? '');
const hasData = computed(() => itemSales.value.length > 0);

// Check if an item is in the top 5
const topItemNames = computed(() => new Set(topItems.value.map(item => item.name)));
function isTopItem(itemName) {
    return topItemNames.value.has(itemName);
}

// Get rank within top 5 (1-based)
function getTopRank(itemName) {
    const index = topItems.value.findIndex(item => item.name === itemName);
    return index >= 0 ? index + 1 : null;
}

// Month picker value (YYYY-MM format)
const monthPickerValue = computed({
    get() {
        const m = String(selectedMonth.value).padStart(2, '0');
        return `${selectedYear.value}-${m}`;
    },
    set(val) {
        if (val) {
            const [y, m] = val.split('-');
            selectedYear.value = parseInt(y, 10);
            selectedMonth.value = parseInt(m, 10);
        }
    },
});

// --- Navigation ---
function selectPeriod(period) {
    activePeriod.value = period;
    fetchReport();
}

function fetchReport() {
    const params = { period: activePeriod.value };

    switch (activePeriod.value) {
        case 'daily':
            params.date = selectedDate.value;
            break;
        case 'weekly':
            params.start_date = selectedStartDate.value;
            break;
        case 'monthly':
            params.year = selectedYear.value;
            params.month = selectedMonth.value;
            break;
        case 'yearly':
            params.year = selectedYear.value;
            break;
    }

    router.get('/reports/sales', params, {
        preserveState: true,
        preserveScroll: true,
    });
}

function onDateChange() {
    fetchReport();
}
</script>

<template>
    <div class="min-h-screen bg-brand-black p-4 sm:p-6 lg:p-8">
        <!-- Header -->
        <header class="mb-8">
            <h1 class="text-2xl sm:text-3xl font-bold text-white">Sales Report</h1>
            <p class="mt-1 text-brand-gray-mid">
                {{ periodLabel || 'View revenue and popular dishes across time periods' }}
            </p>
        </header>

        <!-- Period Selector -->
        <nav class="flex flex-wrap gap-2 mb-6" aria-label="Report period selector">
            <button
                v-for="period in periods"
                :key="period.key"
                type="button"
                :class="activePeriod === period.key ? 'btn-primary' : 'btn-secondary'"
                :aria-pressed="activePeriod === period.key"
                @click="selectPeriod(period.key)"
            >
                {{ period.label }}
            </button>
        </nav>

        <!-- Date Picker (contextual based on period) -->
        <section class="card mb-6" aria-labelledby="date-filter-heading">
            <h2 id="date-filter-heading" class="sr-only">Date Filter</h2>

            <!-- Daily: date picker -->
            <div v-if="activePeriod === 'daily'" class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                <label for="report-date" class="text-sm font-medium text-brand-gray-light">
                    Select Date
                </label>
                <input
                    id="report-date"
                    v-model="selectedDate"
                    type="date"
                    class="input-field w-full sm:w-auto"
                    @change="onDateChange"
                />
            </div>

            <!-- Weekly: start date picker -->
            <div v-else-if="activePeriod === 'weekly'" class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                <label for="report-start-date" class="text-sm font-medium text-brand-gray-light">
                    Week Starting
                </label>
                <input
                    id="report-start-date"
                    v-model="selectedStartDate"
                    type="date"
                    class="input-field w-full sm:w-auto"
                    @change="onDateChange"
                />
            </div>

            <!-- Monthly: month picker -->
            <div v-else-if="activePeriod === 'monthly'" class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                <label for="report-month" class="text-sm font-medium text-brand-gray-light">
                    Select Month
                </label>
                <input
                    id="report-month"
                    v-model="monthPickerValue"
                    type="month"
                    class="input-field w-full sm:w-auto"
                    @change="onDateChange"
                />
            </div>

            <!-- Yearly: year picker -->
            <div v-else-if="activePeriod === 'yearly'" class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                <label for="report-year" class="text-sm font-medium text-brand-gray-light">
                    Select Year
                </label>
                <input
                    id="report-year"
                    v-model.number="selectedYear"
                    type="number"
                    min="2020"
                    :max="new Date().getFullYear()"
                    class="input-field w-full sm:w-auto"
                    @change="onDateChange"
                />
            </div>
        </section>

        <!-- Summary Cards -->
        <section class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8" aria-labelledby="summary-heading">
            <h2 id="summary-heading" class="sr-only">Sales Summary</h2>

            <!-- Total Revenue Card -->
            <div class="card flex flex-col items-center justify-center text-center py-8">
                <span class="text-sm font-medium text-brand-gray-mid uppercase tracking-wide">Total Revenue</span>
                <span class="mt-2 text-3xl sm:text-4xl font-bold text-brand-red-accent">
                    ₹{{ totalRevenue }}
                </span>
            </div>

            <!-- Total Orders Card -->
            <div class="card flex flex-col items-center justify-center text-center py-8">
                <span class="text-sm font-medium text-brand-gray-mid uppercase tracking-wide">Total Orders</span>
                <span class="mt-2 text-3xl sm:text-4xl font-bold text-white">
                    {{ totalOrders }}
                </span>
            </div>
        </section>

        <!-- Top 5 Most Popular Dishes -->
        <section v-if="hasData && topItems.length > 0" class="card mb-8" aria-labelledby="top-items-heading">
            <h2 id="top-items-heading" class="text-xl font-semibold text-white mb-4">
                🔥 Top 5 Most Popular Dishes
            </h2>
            <ol class="space-y-3">
                <li
                    v-for="(item, index) in topItems"
                    :key="item.name"
                    class="flex items-center justify-between py-2 px-3 rounded-lg bg-brand-black-lighter"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-brand-red text-white text-sm font-bold flex-shrink-0"
                            :aria-label="'Rank ' + (index + 1)"
                        >
                            {{ index + 1 }}
                        </span>
                        <span class="text-white font-medium">{{ item.name }}</span>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <span class="text-brand-gray-light text-sm">{{ item.quantity_sold }} sold</span>
                    </div>
                </li>
            </ol>
        </section>

        <!-- Item-wise Sales Table -->
        <section v-if="hasData" class="card" aria-labelledby="item-sales-heading">
            <h2 id="item-sales-heading" class="text-xl font-semibold text-white mb-4">
                Item-wise Sales
            </h2>

            <div class="overflow-x-auto -mx-6 px-6">
                <table class="w-full text-sm" aria-label="Item-wise sales breakdown">
                    <thead>
                        <tr class="border-b border-brand-black-lighter">
                            <th class="text-left text-brand-gray-mid py-3 pr-4 font-medium">Item Name</th>
                            <th class="text-right text-brand-gray-mid py-3 px-3 font-medium">Qty Sold</th>
                            <th class="text-right text-brand-gray-mid py-3 pl-4 font-medium">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(item, index) in itemSales"
                            :key="index"
                            :class="[
                                'border-b border-brand-black-lighter/50 last:border-0',
                                isTopItem(item.name) ? 'bg-brand-red/10' : ''
                            ]"
                        >
                            <td class="py-3 pr-4 text-white">
                                <span class="flex items-center gap-2">
                                    {{ item.name }}
                                    <span
                                        v-if="isTopItem(item.name)"
                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-brand-red text-white"
                                        :aria-label="'Top ' + getTopRank(item.name) + ' item'"
                                    >
                                        Top {{ getTopRank(item.name) }}
                                    </span>
                                </span>
                            </td>
                            <td class="py-3 px-3 text-right text-brand-gray-light">{{ item.quantity_sold }}</td>
                            <td class="py-3 pl-4 text-right text-brand-gray-light">₹{{ Number(item.revenue).toFixed(2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Empty State -->
        <section v-if="!hasData" class="card text-center py-12" aria-labelledby="empty-state-heading">
            <h2 id="empty-state-heading" class="sr-only">No Data</h2>
            <div class="flex flex-col items-center gap-3">
                <svg class="w-16 h-16 text-brand-gray-mid" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                    />
                </svg>
                <p class="text-lg text-brand-gray-mid font-medium">
                    No sales recorded for this period
                </p>
                <p class="text-sm text-brand-gray-mid">
                    Try selecting a different date or period.
                </p>
            </div>
        </section>
    </div>
</template>
