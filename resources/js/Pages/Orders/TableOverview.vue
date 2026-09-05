<template>
    <div class="min-h-screen bg-brand-black p-4 sm:p-6">
        <header class="mb-6">
            <h1 class="text-2xl sm:text-3xl font-bold text-white">
                Table <span class="text-brand-red">Overview</span>
            </h1>
            <p class="text-brand-gray-mid mt-1">Select a table to manage orders</p>
        </header>

        <!-- Legend -->
        <div class="flex flex-wrap gap-4 mb-6 text-sm">
            <span class="flex items-center gap-2 text-brand-gray-mid">
                <span class="w-3 h-3 rounded-full bg-green-500 inline-block"></span> Vacant
            </span>
            <span class="flex items-center gap-2 text-brand-gray-mid">
                <span class="w-3 h-3 rounded-full bg-brand-red inline-block"></span> Occupied (ordering)
            </span>
            <span class="flex items-center gap-2 text-brand-gray-mid">
                <span class="w-3 h-3 rounded-full bg-yellow-400 inline-block"></span> Bill pending payment
            </span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 sm:gap-4">
            <Link
                v-for="table in tables"
                :key="table.id"
                :href="tableHref(table)"
                class="card flex flex-col items-center justify-center min-h-[110px] cursor-pointer
                       transition-all duration-150 hover:scale-[1.02] active:scale-[0.98]
                       focus:outline-none focus:ring-2 focus:ring-brand-btn-focus-ring focus:ring-offset-2 focus:ring-offset-brand-black"
                :class="tableCardClasses(table)"
                :aria-label="`Table ${table.table_number} - ${tableLabel(table)}`"
            >
                <div class="w-3 h-3 rounded-full mb-2" :class="dotClasses(table)" aria-hidden="true"></div>
                <span class="text-lg sm:text-xl font-bold text-white">{{ table.table_number }}</span>
                <span class="text-xs sm:text-sm mt-1 font-medium" :class="labelClasses(table)">
                    {{ tableLabel(table) }}
                </span>
            </Link>
        </div>

        <div v-if="tables.length === 0" class="text-center py-12">
            <p class="text-brand-gray-mid text-lg">No tables configured yet.</p>
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    tables: { type: Array, required: true },
});

function tableHref(table) {
    if (table.has_bill) return `/billing/${table.id}`;
    return `/orders/create/${table.id}`;
}

function tableLabel(table) {
    if (table.has_bill) return 'Bill Pending';
    if (table.status === 'occupied') return 'Occupied';
    return 'Vacant';
}

function tableCardClasses(table) {
    if (table.has_bill) return 'border-yellow-500/60 hover:border-yellow-400';
    if (table.status === 'occupied') return 'border-brand-red/50 hover:border-brand-red';
    return 'border-brand-black-lighter hover:border-brand-gray-mid';
}

function dotClasses(table) {
    if (table.has_bill) return 'bg-yellow-400';
    if (table.status === 'occupied') return 'bg-brand-red';
    return 'bg-green-500';
}

function labelClasses(table) {
    if (table.has_bill) return 'text-yellow-400';
    if (table.status === 'occupied') return 'text-brand-red-accent';
    return 'text-green-400';
}
</script>
