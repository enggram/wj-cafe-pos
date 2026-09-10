<script setup>
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
    bills:      { type: Array,  default: () => [] },
    pagination: { type: Object, default: () => ({ current_page: 1, last_page: 1, per_page: 20, total: 0 }) },
    filters:    { type: Object, default: () => ({ from: '', to: '', table: null, status: null, bill: null, per_page: 20 }) },
    summary:    { type: Object, default: () => ({ total_amount: 0, total_count: 0 }) },
});

const form = reactive({
    from:     props.filters.from     ?? '',
    to:       props.filters.to       ?? '',
    table:    props.filters.table    ?? '',
    status:   props.filters.status   ?? '',
    bill:     props.filters.bill     ?? '',
    per_page: props.filters.per_page ?? 20,
});

function applyFilters(page = 1) {
    const params = {};
    if (form.from)   params.from = form.from;
    if (form.to)     params.to = form.to;
    if (form.table)  params.table = form.table;
    if (form.status) params.status = form.status;
    if (form.bill)   params.bill = form.bill;
    if (form.per_page && Number(form.per_page) !== 20) params.per_page = form.per_page;
    if (page > 1)    params.page = page;

    router.get('/billing-history', params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function resetFilters() {
    form.from = '';
    form.to = '';
    form.table = '';
    form.status = '';
    form.bill = '';
    form.per_page = 20;
    router.get('/billing-history', {}, { preserveScroll: true, replace: true });
}

function goToPage(page) {
    if (page < 1 || page > props.pagination.last_page) return;
    applyFilters(page);
}

function money(v) {
    return Number(v ?? 0).toFixed(2);
}

function formatDateTime(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toLocaleString('en-IN', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}
</script>

<template>
    <div class="min-h-screen bg-brand-black p-4 sm:p-6 lg:p-8">
        <!-- Header -->
        <header class="mb-6">
            <h1 class="text-2xl sm:text-3xl font-bold text-white">Bill History</h1>
            <p class="mt-1 text-brand-gray-mid">Search past bills, reconcile the day, and reprint receipts</p>
        </header>

        <!-- Filters -->
        <section class="card mb-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <div>
                    <label class="block text-xs text-brand-gray-mid mb-1">From</label>
                    <input v-model="form.from" type="date" class="input-field w-full" @change="applyFilters()" />
                </div>
                <div>
                    <label class="block text-xs text-brand-gray-mid mb-1">To</label>
                    <input v-model="form.to" type="date" class="input-field w-full" @change="applyFilters()" />
                </div>
                <div>
                    <label class="block text-xs text-brand-gray-mid mb-1">Table #</label>
                    <input v-model="form.table" type="number" min="1" placeholder="Any" class="input-field w-full" @keyup.enter="applyFilters()" />
                </div>
                <div>
                    <label class="block text-xs text-brand-gray-mid mb-1">Status</label>
                    <select v-model="form.status" class="input-field w-full" @change="applyFilters()">
                        <option value="">All</option>
                        <option value="paid">Paid</option>
                        <option value="unpaid">Unpaid</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-brand-gray-mid mb-1">Bill #</label>
                    <input v-model="form.bill" type="number" min="1" placeholder="Any" class="input-field w-full" @keyup.enter="applyFilters()" />
                </div>
            </div>
            <div class="flex gap-2 mt-3">
                <button type="button" class="btn-primary text-sm" @click="applyFilters()">Apply</button>
                <button type="button" class="btn-secondary text-sm" @click="resetFilters">Reset</button>
            </div>
        </section>

        <!-- Summary -->
        <section class="grid grid-cols-2 gap-4 mb-6">
            <div class="card">
                <p class="text-brand-gray-mid text-sm">Bills Found</p>
                <p class="text-2xl font-bold text-white">{{ summary.total_count }}</p>
            </div>
            <div class="card">
                <p class="text-brand-gray-mid text-sm">Total Amount</p>
                <p class="text-2xl font-bold text-brand-red">₹{{ money(summary.total_amount) }}</p>
            </div>
        </section>

        <!-- Results -->
        <section class="card">
            <div v-if="bills.length === 0" class="py-8 text-center">
                <p class="text-brand-gray-mid">No bills match these filters.</p>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-brand-black-lighter">
                            <th class="text-left text-brand-gray-mid py-2 pr-4">Bill #</th>
                            <th class="text-left text-brand-gray-mid py-2 px-2">Table</th>
                            <th class="text-left text-brand-gray-mid py-2 px-2">Date &amp; Time</th>
                            <th class="text-left text-brand-gray-mid py-2 px-2">Status</th>
                            <th class="text-right text-brand-gray-mid py-2 px-2">Total</th>
                            <th class="text-right text-brand-gray-mid py-2 pl-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="b in bills" :key="b.id" class="border-b border-brand-black-lighter/50 last:border-0">
                            <td class="py-2 pr-4 text-white font-medium">#{{ b.id }}</td>
                            <td class="py-2 px-2 text-brand-gray-light">{{ b.table_number ?? '—' }}</td>
                            <td class="py-2 px-2 text-brand-gray-light">{{ formatDateTime(b.billed_at) }}</td>
                            <td class="py-2 px-2">
                                <span
                                    class="text-xs px-2 py-0.5 rounded-full"
                                    :class="b.status === 'paid'
                                        ? 'bg-green-900/50 text-green-400 border border-green-600'
                                        : 'bg-brand-red/20 text-brand-red-accent border border-brand-red'">
                                    {{ b.status === 'paid' ? 'Paid' : 'Unpaid' }}
                                </span>
                            </td>
                            <td class="py-2 px-2 text-right text-white font-medium">₹{{ money(b.grand_total) }}</td>
                            <td class="py-2 pl-2 text-right">
                                <Link :href="`/billing-history/${b.id}`" class="btn-secondary text-xs">View / Print</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination + per-page control -->
            <div v-if="bills.length > 0" class="flex flex-wrap items-center justify-between gap-3 mt-4 pt-4 border-t border-brand-black-lighter">
                <div class="flex items-center gap-2">
                    <label class="text-brand-gray-mid text-sm">Show</label>
                    <select v-model="form.per_page" class="input-field text-sm py-1" @change="applyFilters()">
                        <option :value="20">20</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                    </select>
                    <span class="text-brand-gray-mid text-sm">per page</span>
                </div>

                <div class="flex items-center gap-3">
                    <button type="button" class="btn-secondary text-sm"
                        :disabled="pagination.current_page <= 1"
                        @click="goToPage(pagination.current_page - 1)">← Prev</button>
                    <span class="text-brand-gray-mid text-sm">
                        Page {{ pagination.current_page }} of {{ pagination.last_page }}
                    </span>
                    <button type="button" class="btn-secondary text-sm"
                        :disabled="pagination.current_page >= pagination.last_page"
                        @click="goToPage(pagination.current_page + 1)">Next →</button>
                </div>
            </div>
        </section>
    </div>
</template>
