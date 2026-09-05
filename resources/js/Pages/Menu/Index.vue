<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    categories:    { type: Array, default: () => [] },
    allCategories: { type: Array, default: () => [] },
});

// ── Category management ────────────────────────────────────────
const showCategoryPanel = ref(false);
const editingCategory   = ref(null);

const categoryForm = useForm({ name: '' });

function startEditCategory(cat) {
    editingCategory.value = cat;
    categoryForm.name = cat.name;
}

function cancelEditCategory() {
    editingCategory.value = null;
    categoryForm.reset();
    categoryForm.clearErrors();
}

function submitCategory() {
    if (editingCategory.value) {
        categoryForm.put(`/categories/${editingCategory.value.id}`, {
            preserveScroll: true,
            onSuccess: () => cancelEditCategory(),
        });
    } else {
        categoryForm.post('/categories', {
            preserveScroll: true,
            onSuccess: () => categoryForm.reset(),
        });
    }
}

function deactivateCategory(cat) {
    router.patch(`/categories/${cat.id}/deactivate`, {}, { preserveScroll: true });
}

function activateCategory(cat) {
    router.patch(`/categories/${cat.id}/activate`, {}, { preserveScroll: true });
}

// ── Menu item management ───────────────────────────────────────
const editingItem = ref(null);

const form = useForm({ name: '', price: '', category_id: '' });

function startEdit(item) {
    editingItem.value = item;
    form.name        = item.name;
    form.price       = item.price;
    form.category_id = item.category_id;
}

function cancelEdit() {
    editingItem.value = null;
    form.reset();
    form.clearErrors();
}

function submitForm() {
    if (editingItem.value) {
        form.put(`/menu/${editingItem.value.id}`, {
            preserveScroll: true,
            onSuccess: () => cancelEdit(),
        });
    } else {
        form.post('/menu', {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }
}

function deactivateItem(item) {
    if (confirm(`Deactivate "${item.name}"?`)) {
        router.patch(`/menu/${item.id}/deactivate`, {}, { preserveScroll: true });
    }
}

// ── Sub-variety management ─────────────────────────────────────
const expandedItems    = ref({});
const subVarietyForms  = ref({});

function toggleSubVarieties(itemId) {
    expandedItems.value[itemId] = !expandedItems.value[itemId];
    if (!subVarietyForms.value[itemId]) {
        subVarietyForms.value[itemId] = useForm({ name: '', price_adjustment: '' });
    }
}

function submitSubVariety(itemId) {
    const subForm = subVarietyForms.value[itemId];
    subForm.post(`/menu/${itemId}/sub-varieties`, {
        preserveScroll: true,
        onSuccess: () => subForm.reset(),
    });
}
</script>

<template>
    <div class="min-h-screen bg-brand-black p-4 sm:p-6 lg:p-8">

        <header class="mb-8 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-white">Menu Management</h1>
                <p class="mt-1 text-brand-gray-mid">Add and manage your categories and menu items</p>
            </div>
            <button
                type="button"
                class="btn-secondary text-sm"
                @click="showCategoryPanel = !showCategoryPanel"
            >
                {{ showCategoryPanel ? '✕ Hide Categories' : '⚙ Manage Categories' }}
            </button>
        </header>

        <!-- ── Category Panel ─────────────────────────────────── -->
        <section v-if="showCategoryPanel" class="card mb-8 border-brand-red/30">
            <h2 class="text-xl font-semibold text-white mb-4">Categories</h2>

            <!-- Add / Edit category form -->
            <form @submit.prevent="submitCategory" class="flex flex-col sm:flex-row gap-3 mb-6">
                <div class="flex-1">
                    <input
                        v-model="categoryForm.name"
                        type="text"
                        :class="categoryForm.errors.name ? 'input-field-error' : 'input-field'"
                        class="w-full"
                        :placeholder="editingCategory ? 'New category name' : 'Category name (e.g. Snacks)'"
                        maxlength="100"
                    />
                    <p v-if="categoryForm.errors.name" class="mt-1 text-sm text-brand-red-light" role="alert">
                        {{ categoryForm.errors.name }}
                    </p>
                </div>
                <button type="submit" class="btn-primary" :disabled="categoryForm.processing">
                    {{ categoryForm.processing ? 'Saving...' : (editingCategory ? 'Update' : 'Add Category') }}
                </button>
                <button v-if="editingCategory" type="button" class="btn-secondary" @click="cancelEditCategory">
                    Cancel
                </button>
            </form>

            <!-- Category list -->
            <div class="space-y-2">
                <div
                    v-for="cat in allCategories"
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
                            class="btn-secondary text-xs px-3 py-1 min-h-[32px]"
                            @click="startEditCategory(cat)"
                        >Edit</button>
                        <button
                            v-if="cat.is_active"
                            type="button"
                            class="btn-secondary text-xs px-3 py-1 min-h-[32px] text-brand-red-light hover:bg-brand-red hover:text-white"
                            @click="deactivateCategory(cat)"
                        >Deactivate</button>
                        <button
                            v-else
                            type="button"
                            class="btn-secondary text-xs px-3 py-1 min-h-[32px] text-green-400 hover:bg-green-700 hover:text-white"
                            @click="activateCategory(cat)"
                        >Activate</button>
                    </div>
                </div>
                <p v-if="allCategories.length === 0" class="text-brand-gray-mid text-sm text-center py-4">
                    No categories yet. Add one above.
                </p>
            </div>
        </section>

        <!-- ── Add / Edit Menu Item Form ──────────────────────── -->
        <section class="card mb-8">
            <h2 class="text-xl font-semibold text-white mb-4">
                {{ editingItem ? 'Edit Menu Item' : 'Add New Menu Item' }}
            </h2>

            <form @submit.prevent="submitForm" class="space-y-4">
                <div>
                    <label for="item-name" class="block text-sm font-medium text-brand-gray-light mb-1">Item Name</label>
                    <input
                        id="item-name"
                        v-model="form.name"
                        type="text"
                        :class="form.errors.name ? 'input-field-error' : 'input-field'"
                        class="w-full"
                        placeholder="e.g. Green Tea"
                        maxlength="100"
                    />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-brand-red-light" role="alert">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label for="item-price" class="block text-sm font-medium text-brand-gray-light mb-1">Price (₹)</label>
                    <input
                        id="item-price"
                        v-model="form.price"
                        type="number" step="0.01" min="0.01" max="99999.99"
                        :class="form.errors.price ? 'input-field-error' : 'input-field'"
                        class="w-full"
                        placeholder="0.00"
                    />
                    <p v-if="form.errors.price" class="mt-1 text-sm text-brand-red-light" role="alert">{{ form.errors.price }}</p>
                </div>

                <div>
                    <label for="item-category" class="block text-sm font-medium text-brand-gray-light mb-1">Category</label>
                    <select
                        id="item-category"
                        v-model="form.category_id"
                        :class="form.errors.category_id ? 'input-field-error' : 'input-field'"
                        class="w-full"
                    >
                        <option value="" disabled>Select a category</option>
                        <option v-for="cat in allCategories.filter(c => c.is_active)" :key="cat.id" :value="cat.id">
                            {{ cat.name }}
                        </option>
                    </select>
                    <p v-if="form.errors.category_id" class="mt-1 text-sm text-brand-red-light" role="alert">{{ form.errors.category_id }}</p>
                </div>

                <div class="flex flex-wrap gap-3 pt-2">
                    <button type="submit" class="btn-primary" :disabled="form.processing">
                        {{ form.processing ? 'Saving...' : (editingItem ? 'Update Item' : 'Add Item') }}
                    </button>
                    <button v-if="editingItem" type="button" class="btn-secondary" @click="cancelEdit">Cancel</button>
                </div>
            </form>
        </section>

        <!-- ── Menu Items List ────────────────────────────────── -->
        <section>
            <h2 class="text-xl font-semibold text-white mb-4">Menu Items</h2>

            <div v-if="categories.length === 0" class="card text-center py-8">
                <p class="text-brand-gray-mid">No menu items yet. Add categories first, then add items above.</p>
            </div>

            <div v-for="category in categories" :key="category.id" class="mb-6">
                <h3 class="text-lg font-semibold text-brand-red-accent mb-3 border-b border-brand-black-lighter pb-2">
                    {{ category.name }}
                </h3>

                <p v-if="!category.menu_items || category.menu_items.length === 0" class="pl-4 text-brand-gray-mid text-sm italic">
                    No items in this category
                </p>

                <div class="space-y-3">
                    <div v-for="item in category.menu_items" :key="item.id" class="card">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div class="flex-1">
                                <h4 class="text-white font-medium">{{ item.name }}</h4>
                                <p class="text-brand-gray-light text-sm">₹{{ Number(item.price).toFixed(2) }}</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="btn-secondary text-sm" @click="startEdit(item)">Edit</button>
                                <button
                                    type="button"
                                    class="btn-secondary text-sm text-brand-red-light border-brand-red hover:bg-brand-red hover:text-white"
                                    @click="deactivateItem(item)"
                                >Deactivate</button>
                                <button
                                    type="button"
                                    class="btn-secondary text-sm"
                                    @click="toggleSubVarieties(item.id)"
                                >{{ expandedItems[item.id] ? 'Hide Varieties' : 'Sub-Varieties' }}</button>
                            </div>
                        </div>

                        <!-- Sub-varieties list -->
                        <div v-if="item.sub_varieties && item.sub_varieties.length > 0" class="mt-3 pl-4 border-l-2 border-brand-black-lighter">
                            <p class="text-xs text-brand-gray-mid uppercase tracking-wide mb-2">Sub-Varieties</p>
                            <ul class="space-y-1">
                                <li v-for="sv in item.sub_varieties" :key="sv.id" class="flex items-center justify-between text-sm">
                                    <span class="text-brand-gray-light">{{ sv.name }}</span>
                                    <span v-if="Number(sv.price_adjustment) !== 0" class="text-brand-gray-mid">
                                        {{ Number(sv.price_adjustment) > 0 ? '+' : '' }}₹{{ Number(sv.price_adjustment).toFixed(2) }}
                                    </span>
                                </li>
                            </ul>
                        </div>

                        <!-- Add sub-variety form -->
                        <div v-if="expandedItems[item.id]" class="mt-4 pt-4 border-t border-brand-black-lighter">
                            <p class="text-sm font-medium text-brand-gray-light mb-3">Add Sub-Variety</p>
                            <form @submit.prevent="submitSubVariety(item.id)" class="flex flex-col sm:flex-row gap-3">
                                <input
                                    v-model="subVarietyForms[item.id].name"
                                    type="text"
                                    :class="subVarietyForms[item.id]?.errors?.name ? 'input-field-error' : 'input-field'"
                                    class="flex-1"
                                    placeholder="Variety name"
                                    maxlength="100"
                                />
                                <input
                                    v-model="subVarietyForms[item.id].price_adjustment"
                                    type="number" step="0.01"
                                    :class="subVarietyForms[item.id]?.errors?.price_adjustment ? 'input-field-error' : 'input-field'"
                                    class="w-full sm:w-32"
                                    placeholder="± Price"
                                />
                                <button type="submit" class="btn-primary text-sm" :disabled="subVarietyForms[item.id]?.processing">
                                    {{ subVarietyForms[item.id]?.processing ? 'Adding...' : 'Add' }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
