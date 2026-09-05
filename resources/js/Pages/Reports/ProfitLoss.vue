<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    report: {
        type: Object,
        default: () => ({
            totalEarnings: 0,
            totalSpending: 0,
            netAmount: 0,
            status: 'break-even',
            periodLabel: '',
        }),
    },
    formatted: {
        type: Object,
        default: () => ({
            earnings: '₹0.00',
            spending: '₹0.00',
            net: '₹0.00',
        }),
    },
    filters: {
        type: Object,
        default: () => ({
            period: 'monthly',
            year: new Date().getFullYear(),
            month: new Date().getMonth() + 1,
            week_start: null,
        }),
    },
});

// --- Period selector ---
const periods = [
    { value: 'weekly', label: 'Weekly' },
    { value: 'monthly', label: 'Monthly' },
    { value: 'yearly', label: 'Yearly' },
];

const selectedPeriod = ref(props.filters.period || 'monthly');
const selectedYear = ref(props.filters.year || new Date().getFullYear());
const selectedMonth = ref(props.filters.month || new Date().getMonth() + 1);
const selectedWeekStart = ref(props.filters.week_start || getMondayOfCurrentWeek());

function getMondayOfCurrentWeek() {
    const now = new Date();
    const day = now.getDay();
    const diff = now.getDate() - day + (day === 0 ? -6 : 1);
    const monday = new Date(now.setDate(diff));
    return monday.toISOString().split('T')[0];
}

// --- Available years for dropdown ---
const availableYears = computed(() => {
    const currentYear = new Date().getFullYear();
    const years = [];
    for (let y = currentYear; y >= currentYear - 5; y--) {
        years.push(y);
    }
    return years;
});

// --- Month labels ---
const months = [
    { value: 1, label: 'January' },
    { value: 2, label: 'February' },
    { value: 3, label: 'March' },
    { value: 4, label: 'April' },
    { value: 5, label: 'May' },
    { value: 6, label: 'June' },
    { value: 7, label: 'July' },
    { value: 8, label: 'August' },
    { value: 9, label: 'September' },
    { value: 10, label: 'October' },
    { value: 11, label: 'November' },
    { value: 12, label: 'December' },
];

// --- Navigate on filter change ---
function applyFilters() {
    const params = { period: selectedPeriod.value };

    if (selectedPeriod.value === 'weekly') {
        params.week_start = selectedWeekStart.value;
    } else if (selectedPeriod.value === 'monthly') {
        params.year = selectedYear.value;
        params.month = selectedMonth.value;
    } else if (selectedPeriod.value === 'yearly') {
        params.year = selectedYear.value;
    }

    router.get('/reports/profit-loss', params, {
        preserveState: true,
        preserveScroll: true,
    });
}

function changePeriod(period) {
    selectedPeriod.value = period;
    applyFilters();
}

// --- Status indicator config ---
const statusConfig = computed(() => {
    const status = props.report.status;
    if (status === 'profit') {
        return {
            label: 'Profit',
            bgClass: 'bg-green-700',
            textClass: 'text-white',
            borderClass: 'border-green-600',
            iconPath: 'M5 10l7-7m0 0l7 7m-7-7v18', // upward arrow
            ariaLabel: 'Status: Profit',
        };
    } else if (status === 'loss') {
        return {
            label: 'Loss',
            bgClass: 'bg-red-700',
            textClass: 'text-white',
            borderClass: 'border-red-600',
            iconPath: 'M19 14l-7 7m0 0l-7-7m7 7V3', // downward arrow
            ariaLabel: 'Status: Loss',
        };
    }
    return {
        label: 'Break Even',
        bgClass: 'bg-neutral-700',
        textClass: 'text-white',
        borderClass: 'border-neutral-500',
        iconPath: 'M4 12h16', // equals/horizontal line
        ariaLabel: 'Status: Break Even',
    };
});
</script>

<template>
    <div class="min-h-screen bg-brand-black p-4 sm:p-6 lg:p-8">
        <!-- Header -->
        <header class="mb-8">
            <h1 class="text-2xl sm:text-3xl font-bold text-white">Profit &amp; Loss Report</h1>
            <p class="mt-1 text-brand-gray-mid">
                Compare earnings against spending across time periods
            </p>
        </header>

        <!-- Period Selector -->
        <section class="card mb-6" aria-labelledby="period-selector-heading">
            <h2 id="period-selector-heading" class="sr-only">Report Period Selection</h2>

            <!-- Period type buttons -->
            <div class="flex flex-wrap gap-2 mb-4" role="group" aria-label="Report period type">
                <button
                    v-for="period in periods"
                    :key="period.value"
                    type="button"
                    :class="selectedPeriod === period.value ? 'btn-primary' : 'btn-secondary'"
                    :aria-pressed="selectedPeriod === period.value"
                    @click="changePeriod(period.value)"
                >
                    {{ period.label }}
                </button>
            </div>

            <!-- Date picker based on period type -->
            <div class="flex flex-col sm:flex-row sm:flex-wrap sm:items-end gap-4">
                <!-- Weekly: date picker for week start -->
                <div v-if="selectedPeriod === 'weekly'" class="w-full sm:w-auto">
                    <label for="week-start-date" class="block text-sm font-medium text-brand-gray-light mb-1">
                        Week Starting (Monday)
                    </label>
                    <input
                        id="week-start-date"
                        v-model="selectedWeekStart"
                        type="date"
                        class="input-field w-full sm:w-auto"
                        @change="applyFilters"
                    />
                </div>

                <!-- Monthly: month and year pickers -->
                <template v-if="selectedPeriod === 'monthly'">
                    <div class="w-full sm:w-auto">
                        <label for="report-month" class="block text-sm font-medium text-brand-gray-light mb-1">
                            Month
                        </label>
                        <select
                            id="report-month"
                            v-model="selectedMonth"
                            class="input-field w-full sm:min-w-[140px]"
                            @change="applyFilters"
                        >
                            <option
                                v-for="m in months"
                                :key="m.value"
                                :value="m.value"
                            >
                                {{ m.label }}
                            </option>
                        </select>
                    </div>
                    <div class="w-full sm:w-auto">
                        <label for="report-year-monthly" class="block text-sm font-medium text-brand-gray-light mb-1">
                            Year
                        </label>
                        <select
                            id="report-year-monthly"
                            v-model="selectedYear"
                            class="input-field w-full sm:min-w-[100px]"
                            @change="applyFilters"
                        >
                            <option
                                v-for="y in availableYears"
                                :key="y"
                                :value="y"
                            >
                                {{ y }}
                            </option>
                        </select>
                    </div>
                </template>

                <!-- Yearly: year picker only -->
                <div v-if="selectedPeriod === 'yearly'" class="w-full sm:w-auto">
                    <label for="report-year-yearly" class="block text-sm font-medium text-brand-gray-light mb-1">
                        Year
                    </label>
                    <select
                        id="report-year-yearly"
                        v-model="selectedYear"
                        class="input-field w-full sm:min-w-[100px]"
                        @change="applyFilters"
                    >
                        <option
                            v-for="y in availableYears"
                            :key="y"
                            :value="y"
                        >
                            {{ y }}
                        </option>
                    </select>
                </div>
            </div>
        </section>

        <!-- Period Label -->
        <p v-if="report.periodLabel" class="text-brand-gray-light text-sm mb-4">
            Showing report for: <span class="font-semibold text-white">{{ report.periodLabel }}</span>
        </p>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <!-- Total Earnings -->
            <div class="card">
                <p class="text-sm font-medium text-brand-gray-mid mb-1">Total Earnings</p>
                <p class="text-2xl font-bold text-white" aria-label="Total earnings">
                    {{ formatted.earnings }}
                </p>
            </div>

            <!-- Total Spending -->
            <div class="card">
                <p class="text-sm font-medium text-brand-gray-mid mb-1">Total Spending</p>
                <p class="text-2xl font-bold text-white" aria-label="Total spending">
                    {{ formatted.spending }}
                </p>
            </div>

            <!-- Net Amount -->
            <div class="card">
                <p class="text-sm font-medium text-brand-gray-mid mb-1">Net Amount</p>
                <p class="text-2xl font-bold text-white" aria-label="Net amount">
                    {{ formatted.net }}
                </p>
            </div>
        </div>

        <!-- Status Indicator -->
        <div
            class="card flex items-center gap-4 border-2"
            :class="[statusConfig.borderClass]"
            role="status"
            :aria-label="statusConfig.ariaLabel"
        >
            <!-- Icon with colored background -->
            <div
                class="flex items-center justify-center w-12 h-12 rounded-full shrink-0"
                :class="[statusConfig.bgClass]"
                aria-hidden="true"
            >
                <svg
                    class="w-6 h-6"
                    :class="[statusConfig.textClass]"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    viewBox="0 0 24 24"
                >
                    <path :d="statusConfig.iconPath" />
                </svg>
            </div>

            <!-- Status text -->
            <div>
                <span
                    class="inline-block px-3 py-1 rounded-full text-sm font-bold"
                    :class="[statusConfig.bgClass, statusConfig.textClass]"
                >
                    {{ statusConfig.label }}
                </span>
                <p class="mt-1 text-brand-gray-light text-sm">
                    <template v-if="report.status === 'profit'">
                        Earnings exceeded spending this period.
                    </template>
                    <template v-else-if="report.status === 'loss'">
                        Spending exceeded earnings this period.
                    </template>
                    <template v-else>
                        Earnings and spending are equal this period.
                    </template>
                </p>
            </div>
        </div>
    </div>
</template>
