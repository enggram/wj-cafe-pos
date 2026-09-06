<template>
    <div class="min-h-screen bg-brand-black p-4 sm:p-6">

        <!-- Paid confirmation banner -->
        <div
            v-if="isSettled"
            class="mb-6 p-4 rounded-lg bg-green-900/50 border border-green-600 flex items-center gap-3"
        >
            <span class="text-2xl">✓</span>
            <div>
                <p class="text-green-400 font-bold text-lg">Payment confirmed!</p>
                <p class="text-green-300 text-sm">Table {{ table.table_number }} is now free.</p>
            </div>
        </div>

        <!-- Header -->
        <header class="mb-6 flex items-center justify-between flex-wrap gap-2">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-white">
                    Bill <span class="text-brand-red">#{{ bill.id }}</span>
                </h1>
                <p class="text-brand-gray-mid mt-1">
                    Table {{ table.table_number }} · {{ formattedDate }} {{ formattedTime }}
                </p>
            </div>
            <div class="flex gap-2 flex-wrap">
                <button type="button" class="btn-secondary text-sm" @click="printBill">🖨️ Print</button>
                <Link href="/orders/tables" class="btn-secondary text-sm">← All Tables</Link>
            </div>
        </header>

        <!-- Bill card -->
        <div id="printable-bill" class="card mb-6">
            <!-- Receipt header -->
            <div class="text-center mb-4 pb-4 border-b border-brand-black-lighter">
                <p class="text-white font-bold text-lg">WhiteJersey Cafe</p>
                <p class="text-brand-gray-mid text-sm">
                    Table {{ table.table_number }} · Bill #{{ bill.id }}
                </p>
                <p class="text-brand-gray-mid text-sm">{{ formattedDate }} {{ formattedTime }}</p>
            </div>

            <!-- Line items -->
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-brand-black-lighter">
                            <th class="text-left text-brand-gray-mid py-2 pr-4">Item</th>
                            <th class="text-center text-brand-gray-mid py-2 px-2">Qty</th>
                            <th class="text-right text-brand-gray-mid py-2 px-2">Price</th>
                            <th class="text-right text-brand-gray-mid py-2 pl-2">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in bill.order.order_items"
                            :key="item.id"
                            class="border-b border-brand-black-lighter/50 last:border-0"
                        >
                            <td class="py-2 pr-4 text-white">
                                {{ item.menu_item?.name ?? `Item #${item.id}` }}
                                <span v-if="item.is_parcel" class="ml-2 text-xs px-1.5 py-0.5 rounded bg-brand-red/20 text-brand-red-accent">Parcel</span>
                                <span v-if="item.is_parcel && Number(item.parcel_rate) > 0" class="block text-xs text-brand-gray-mid">
                                    incl. ₹{{ fmt(item.parcel_rate) }}/unit parcel
                                </span>
                            </td>
                            <td class="py-2 px-2 text-center text-brand-gray-light">{{ item.quantity }}</td>
                            <td class="py-2 px-2 text-right text-brand-gray-light">₹{{ fmt(effectiveUnitPrice(item)) }}</td>
                            <td class="py-2 pl-2 text-right text-white font-medium">
                                ₹{{ fmt(lineTotal(item)) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Total (parcel charges are folded into each line's price) -->
            <div class="mt-4">
                <div class="pt-4 border-t-2 border-brand-red flex items-center justify-between">
                    <span class="text-lg font-bold text-white">Grand Total</span>
                    <span class="text-2xl sm:text-3xl font-bold text-brand-red">
                        ₹{{ fmt(bill.grand_total) }}
                    </span>
                </div>
            </div>

            <!-- PAID stamp -->
            <div v-if="isSettled" class="mt-4 text-center">
                <span class="inline-block border-2 border-green-500 text-green-400 font-bold text-xl px-6 py-2 rounded rotate-[-8deg]">
                    PAID
                </span>
            </div>
        </div>

        <!-- Action buttons -->
        <div class="flex flex-wrap items-center gap-3">
            <template v-if="isSettled">
                <Link href="/orders/tables" class="btn-primary px-8">← Back to Tables</Link>
            </template>
            <template v-else>
                <button
                    type="button"
                    class="btn-primary px-8 py-3 text-lg"
                    :disabled="settleForm.processing"
                    @click="settleBill"
                >
                    {{ settleForm.processing ? 'Processing...' : '✓ Mark as Paid' }}
                </button>
                <span class="text-brand-gray-mid text-sm">Table {{ table.table_number }} will be freed</span>
            </template>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    bill:    { type: Object, required: true },
    table:   { type: Object, required: true },
    settled: { type: Boolean, default: false },
});

const page        = usePage();
const settleForm  = useForm({});

// Paid if server says settled, or bill status is already paid
const isSettled = computed(() =>
    props.settled ||
    props.bill.status === 'paid' ||
    !!page.props.flash?.settled
);

function fmt(value) {
    return Number(value).toFixed(2);
}

// Effective per-unit price includes the parcel rate for parcel lines
function effectiveUnitPrice(item) {
    const base = Number(item.unit_price);
    const parcel = item.is_parcel ? Number(item.parcel_rate || 0) : 0;
    return base + parcel;
}

// Line total = effective unit price x quantity
function lineTotal(item) {
    return effectiveUnitPrice(item) * item.quantity;
}

const formattedDate = computed(() => {
    if (!props.bill.billed_at) return '';
    return new Date(props.bill.billed_at).toLocaleDateString('en-IN', {
        year: 'numeric', month: 'short', day: 'numeric',
    });
});

const formattedTime = computed(() => {
    if (!props.bill.billed_at) return '';
    return new Date(props.bill.billed_at).toLocaleTimeString('en-IN', {
        hour: '2-digit', minute: '2-digit',
    });
});

function settleBill() {
    settleForm.post(`/billing/${props.bill.id}/settle`);
}

function printBill() {
    window.print();
}
</script>

<style>
@media print {
    body * { visibility: hidden; }
    #printable-bill, #printable-bill * { visibility: visible; }
    #printable-bill {
        position: absolute; top: 0; left: 0; width: 100%;
        background: white !important;
        color: black !important;
        border: none !important;
    }
}
</style>
