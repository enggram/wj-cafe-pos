<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    categories: {
        type: Array,
        default: () => [],
    },
    activeCategories: {
        type: Array,
        default: () => [],
    },
    dailyExpenses: {
        type: Object,
        default: () => ({
            date: new Date().toISOString().split('T')[0],
            categories: [],
            grandTotal: 0,
        }),
    },
    monthlyExpenses: {
        type: Object,
        default: () => ({
            year: new Date().getFullYear(),
            month: new Date().getMonth() + 1,
            categoryTotals: [],
            grandTotal: 0,
        }),
    },
});

const today = new Date().toISOString().split('T')[0];

// ── Tab management ─────────────────────────────────────────────
const activeTab = ref('daily');

// ── Category management ────────────────────────────────────────
const showCategoryPanel = ref(false);
const editingCategory = ref(null);

const categoryForm = useForm({ name: '' });

function startEditCategory(cat) {
    editingCategory.value = cat;
    categoryForm.name = cat.name;
    categoryForm.clearErrors();
}

function cancelEditCategory() {
    editingCategory.value = null;
    categoryForm.reset();
    categoryForm.clearErrors();
}

function submitCategory() {
    if (editingCategory.value) {
        categoryForm.put(`/expense-categories/${editingCategory.value.id}`, {
            preserveScroll: true,
            onSuccess: () => cancelEditCategory(),
        });
    } else {
        categoryForm.post('/expense-categories', {
            preserveScroll: true,
            onSuccess: () => categoryForm.reset(),
        });
    }
}

function deactivateCategory(cat) {
    router.patch(`/expense-categories/${cat.id}/deactivate`, {}, { preserveScroll: true });
}

function activateCategory(cat) {
    router.patch(`/expense-categories/${cat.id}/activate`, {}, { preserveScroll: true });
}

// ── Expense entry form ─────────────────────────────────────────
const form = useForm({
    expense_category_id: '',
    amount: '',
    description: '',
    expense_date: today,
});

function submitExpense() {
    form.post('/expenses', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.expense_date = today;
        },
    });
}

// ── Currency helper ────────────────────────────────────────────
function money(value) {
    return '₹' + Number(value ?? 0).toFixed(2);
}

// ── Daily computed helpers ─────────────────────────────────────
const dailyCategories = computed(() => props.dailyExpenses?.categories ?? []);
const dailyGrandTotal = computed(() => money(props.dailyExpenses?.grandTotal ?? 0));
const dailyDate = computed(() => props.dailyExpenses?.date ?? today);

// ── Monthly computed helpers ───────────────────────────────────
const monthlyTotals = computed(() => props.monthlyExpenses?.categoryTotals ?? []);
const monthlyGrandTotal = computed(() => money(props.monthlyExpenses?.grandTotal ?? 0));
const monthlyLabel = computed(() => {
    if (!props.monthlyExpenses) return '';
    const date = new Date(props.monthlyExpenses.year, props.monthlyExpenses.month - 1);
    return date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
});
</script>

<template>
    <div class="min-h-screen bg-brand-black p-4 sm:p-6 lg:p-8">
        <!-- Header -->
        <header class="mb-8 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-white">Expenses</h1>
                <p class="mt-1 text-brand-gray-mid">Record and track daily and monthly expenses</p>
            </div>
            <button
                type="button"
                class="btn-secondary text-sm min-h-[44px]"
                @click="showCategoryPanel = !showCategoryPanel"
            >
                {{ showCategoryPanel ? '✕ Hide Categories' : '⚙ Manage Categories' }}
            </button>
        </header>

        <!-- ── Category Panel ─────────────────────────────────── -->
        <section v-if="showCategoryPanel" class="card mb-8 border-brand-red/30">
            <h2 class="text-xl font-semibold text-white mb-4">Expense Categories</h2>

            <!-- Add / Edit category form -->
            <form @submit.prevent="submitCategory" class="flex flex-col sm:flex-row gap-3 mb-6">
                <div class="flex-1">
                    <input
                        v-model="categoryForm.name"
                        type="text"
                        :class="categoryForm.errors.name ? 'input-field-error' : 'input-field'"
                        class="w-full"
                        :placeholder="editingCategory ? 'New category name' : 'Category name (e.g. Rent)'"
                        maxlength="100"
                    />
                    <p v-if="categoryForm.errors.name" class="mt-1 text-sm text-brand-red-light" role="alert">
                        {{ categoryForm.errors.name }}
                    </p>
                </div>
                <button type="submit" class="btn-primary min-h-[44px]" :disabled="categoryForm.processing">
                    {{ categoryForm.processing ? 'Saving...' : (editingCategory ? 'Update' : 'Add Category') }}
                </button>
                <button v-if="editingCategory" type="button" class="btn-secondary min-h-[44px]" @click="cancelEditCategory">
                    Cancel
                </button>
            </form>

            <!-- Category list -->
            <div class="space-y-2">
                <div
                    v-for="cat in categories"
                    :key="cat.id"
                    class="flex items-center justify-between gap-3 px-4 py-3 rounded-lg bg-brand-black-lighter"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="w-2 h-2 rounded-full flex-shrink-0"
                            :class="cat.is_active ? 'bg-green-500' : 'bg-brand-gray-mid'"
                        ></span>
                        <span class="text-white font-medium">{{ cat.name }}</span>
                        <span v-if="!cat.is_active" class="text-xs text-brand-gray-mid">(inactive)</span>
                    </div>
                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="btn-secondary text-xs px-3 py-1 min-h-[44px]"
                            @click="startEditCategory(cat)"
                        >Edit</button>
                        <button
                            v-if="cat.is_active"
                            type="button"
                            class="btn-secondary text-xs px-3 py-1 min-h-[44px] text-brand-red-light hover:bg-brand-red hover:text-white"
                            @click="deactivateCategory(cat)"
                        >Deactivate</button>
                        <button
                            v-else
                            type="button"
                            class="btn-secondary text-xs px-3 py-1 min-h-[44px] text-green-400 hover:bg-green-700 hover:text-white"
                            @click="activateCategory(cat)"
                        >Activate</button>
                    </div>
                </div>
                <p v-if="categories.length === 0" class="text-brand-gray-mid text-sm text-center py-4">
                    No expense categories yet. Add one above.
                </p>
            </div>
        </section>

        <!-- ── Expense Entry Form ─────────────────────────────── -->
        <section class="card mb-8" aria-labelledby="expense-form-heading">
            <h2 id="expense-form-heading" class="text-xl font-semibold text-white mb-4">
                Record Expense
            </h2>

            <form @submit.prevent="submitExpense" class="space-y-4">
                <!-- Category -->
                <div>
                    <label for="expense-category" class="block text-sm font-medium text-brand-gray-light mb-1">
                        Category
                    </label>
                    <select
                        id="expense-category"
                        v-model="form.expense_category_id"
                        :class="form.errors.expense_category_id ? 'input-field-error' : 'input-field'"
                        class="w-full"
                    >
                        <option value="" disabled>Select a category</option>
                        <option v-for="cat in activeCategories" :key="cat.id" :value="cat.id">
                            {{ cat.name }}
                        </option>
                    </select>
                    <p v-if="form.errors.expense_category_id" class="mt-1 text-sm text-brand-red-light" role="alert">
                        {{ form.errors.expense_category_id }}
                    </p>
                </div>

                <!-- Amount -->
                <div>
                    <label for="expense-amount" class="block text-sm font-medium text-brand-gray-light mb-1">
                        Amount (₹)
                    </label>
                    <input
                        id="expense-amount"
                        v-model="form.amount"
                        type="number"
                        step="0.01"
                        min="0.01"
                        max="9999999.99"
                        :class="form.errors.amount ? 'input-field-error' : 'input-field'"
                        class="w-full"
                        placeholder="0.00"
                    />
                    <p v-if="form.errors.amount" class="mt-1 text-sm text-brand-red-light" role="alert">
                        {{ form.errors.amount }}
                    </p>
                </div>

                <!-- Description -->
                <div>
                    <label for="expense-description" class="block text-sm font-medium text-brand-gray-light mb-1">
                        Description <span class="text-brand-gray-mid">(optional)</span>
                    </label>
                    <input
                        id="expense-description"
                        v-model="form.description"
                        type="text"
                        maxlength="255"
                        :class="form.errors.description ? 'input-field-error' : 'input-field'"
                        class="w-full"
                        placeholder="e.g. September rent"
                    />
                    <p v-if="form.errors.description" class="mt-1 text-sm text-brand-red-light" role="alert">
                        {{ form.errors.description }}
                    </p>
                </div>

                <!-- Expense Date -->
                <div>
                    <label for="expense-date" class="block text-sm font-medium text-brand-gray-light mb-1">
                        Expense Date
                    </label>
                    <input
                        id="expense-date"
                        v-model="form.expense_date"
                        type="date"
                        :max="today"
                        :class="form.errors.expense_date ? 'input-field-error' : 'input-field'"
                        class="w-full"
                    />
                    <p v-if="form.errors.expense_date" class="mt-1 text-sm text-brand-red-light" role="alert">
                        {{ form.errors.expense_date }}
                    </p>
                </div>

                <!-- Submit Button -->
                <div class="pt-2">
                    <button
                        type="submit"
                        class="btn-primary min-h-[44px]"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Saving...' : 'Record Expense' }}
                    </button>
                </div>
            </form>
        </section>

        <!-- ── Tab Navigation ─────────────────────────────────── -->
        <nav class="flex gap-2 mb-6" aria-label="Expense view tabs">
            <button
                type="button"
                :class="activeTab === 'daily' ? 'btn-primary' : 'btn-secondary'"
                class="min-h-[44px]"
                @click="activeTab = 'daily'"
            >
                Daily Expenses
            </button>
            <button
                type="button"
                :class="activeTab === 'monthly' ? 'btn-primary' : 'btn-secondary'"
                class="min-h-[44px]"
                @click="activeTab = 'monthly'"
            >
                Monthly Summary
            </button>
        </nav>

        <!-- ── Daily Expenses Section ─────────────────────────── -->
        <section v-if="activeTab === 'daily'" aria-labelledby="daily-expenses-heading">
            <div class="card">
                <h2 id="daily-expenses-heading" class="text-xl font-semibold text-white mb-4">
                    Daily Expenses — {{ dailyDate }}
                </h2>

                <div v-if="dailyCategories.length === 0" class="text-center py-6">
                    <p class="text-brand-gray-mid">No expenses recorded for this date.</p>
                </div>

                <div v-else class="space-y-6">
                    <div
                        v-for="(cat, catIndex) in dailyCategories"
                        :key="catIndex"
                    >
                        <h3 class="text-lg font-semibold text-brand-red-accent mb-2 border-b border-brand-black-lighter pb-2">
                            {{ cat.category_name }}
                        </h3>
                        <div class="overflow-x-auto -mx-6 px-6">
                            <table class="w-full text-sm" aria-label="Daily expense entries">
                                <thead>
                                    <tr class="border-b border-brand-black-lighter">
                                        <th class="text-left text-brand-gray-mid py-2 pr-4 font-medium">Description</th>
                                        <th class="text-right text-brand-gray-mid py-2 pl-4 font-medium">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(entry, entryIndex) in cat.entries"
                                        :key="entryIndex"
                                        class="border-b border-brand-black-lighter/50 last:border-0"
                                    >
                                        <td class="py-2 pr-4 text-white">
                                            {{ entry.description || '—' }}
                                        </td>
                                        <td class="py-2 pl-4 text-right text-brand-gray-light">
                                            {{ money(entry.amount) }}
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="border-t border-brand-red/50">
                                        <td class="py-2 pr-4 text-white font-medium">{{ cat.category_name }} Total</td>
                                        <td class="py-2 pl-4 text-right text-brand-red-accent font-semibold">
                                            {{ money(cat.total) }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Grand Total -->
                    <div class="flex items-center justify-between border-t-2 border-brand-red pt-3">
                        <span class="text-white font-semibold">Grand Total</span>
                        <span class="text-brand-red-accent font-bold text-base">{{ dailyGrandTotal }}</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── Monthly Expenses Section ───────────────────────── -->
        <section v-if="activeTab === 'monthly'" aria-labelledby="monthly-expenses-heading">
            <div class="card">
                <h2 id="monthly-expenses-heading" class="text-xl font-semibold text-white mb-4">
                    Monthly Summary{{ monthlyLabel ? ' — ' + monthlyLabel : '' }}
                </h2>

                <div v-if="monthlyTotals.length === 0" class="text-center py-6">
                    <p class="text-brand-gray-mid">No expenses recorded for this month.</p>
                </div>

                <div v-else>
                    <div class="overflow-x-auto -mx-6 px-6">
                        <table class="w-full text-sm" aria-label="Monthly expense summary by category">
                            <thead>
                                <tr class="border-b border-brand-black-lighter">
                                    <th class="text-left text-brand-gray-mid py-3 pr-4 font-medium">Category</th>
                                    <th class="text-right text-brand-gray-mid py-3 pl-4 font-medium">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(cat, index) in monthlyTotals"
                                    :key="index"
                                    class="border-b border-brand-black-lighter/50 last:border-0"
                                >
                                    <td class="py-3 pr-4 text-white">{{ cat.category_name }}</td>
                                    <td class="py-3 pl-4 text-right text-brand-gray-light">{{ money(cat.total) }}</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="border-t-2 border-brand-red">
                                    <td class="py-3 pr-4 text-white font-semibold">Grand Total</td>
                                    <td class="py-3 pl-4 text-right text-brand-red-accent font-bold text-base">
                                        {{ monthlyGrandTotal }}
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
