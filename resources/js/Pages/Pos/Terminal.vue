<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    terminal: {
        type: Object,
        required: true,
    },
    catalogProducts: {
        type: Array,
        default: () => [],
    },
    paymentMethods: {
        type: Array,
        default: () => [],
    },
    shortcuts: {
        type: Array,
        default: () => [],
    },
});

const state = reactive({
    search: '',
    discount: 0,
    paymentMethod: props.paymentMethods[0]?.key ?? 'cash',
    message: '',
    suspendedSales: [],
});

const cart = ref([]);
const searchInput = ref(null);
const page = usePage();
const suspendedSalesStorageKey = 'pos.suspended_sales';

const moneyFormatter = computed(() => new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: props.terminal.currency ?? 'ARS',
    minimumFractionDigits: 2,
}));

const filteredProducts = computed(() => {
    const term = state.search.trim().toLowerCase();

    if (term === '') {
        return props.catalogProducts.slice(0, 6);
    }

    return props.catalogProducts
        .filter((product) => (
            product.name.toLowerCase().includes(term) ||
            product.code.toLowerCase().includes(term)
        ))
        .slice(0, 8);
});

const subtotal = computed(() => (
    cart.value.reduce((carry, item) => carry + (item.qty * item.price), 0)
));

const discountAmount = computed(() => {
    const normalized = Number(state.discount) || 0;
    const safe = Math.max(0, normalized);

    return Math.min(safe, subtotal.value);
});

const total = computed(() => Math.max(0, subtotal.value - discountAmount.value));

const addProduct = (product) => {
    const found = cart.value.find((item) => item.id === product.id);
    const step = product.unit === 'kg' ? 0.5 : 1;

    if (found) {
        found.qty = Number((found.qty + step).toFixed(2));
    } else {
        cart.value.push({
            id: product.id,
            code: product.code,
            name: product.name,
            unit: product.unit,
            price: Number(product.price),
            qty: step,
        });
    }
};

const removeItem = (itemId) => {
    cart.value = cart.value.filter((item) => item.id !== itemId);
};

const updateQty = (item, value) => {
    const parsed = Number(value);
    if (!Number.isFinite(parsed) || parsed <= 0) {
        removeItem(item.id);
        return;
    }

    item.qty = Number(parsed.toFixed(2));
};

const clearCart = () => {
    cart.value = [];
    state.discount = 0;
    state.message = 'Carrito limpiado';
};

const persistSuspendedSales = () => {
    sessionStorage.setItem(suspendedSalesStorageKey, JSON.stringify(state.suspendedSales));
};

const loadSuspendedSales = () => {
    const rawData = sessionStorage.getItem(suspendedSalesStorageKey);
    if (!rawData) {
        return;
    }

    try {
        const parsed = JSON.parse(rawData);
        state.suspendedSales = Array.isArray(parsed) ? parsed : [];
    } catch {
        state.suspendedSales = [];
    }
};

const suspendSale = () => {
    if (!cart.value.length) {
        state.message = 'No hay venta para suspender';
        return;
    }

    state.suspendedSales.unshift({
        id: Date.now(),
        items: cart.value.map((item) => ({ ...item })),
        discount: discountAmount.value,
        paymentMethod: state.paymentMethod,
        total: total.value,
    });
    persistSuspendedSales();
    cart.value = [];
    state.discount = 0;
    state.message = 'Venta suspendida correctamente';
};

const resumeSale = (suspendedId) => {
    const sale = state.suspendedSales.find((item) => item.id === suspendedId);
    if (!sale) {
        return;
    }

    cart.value = sale.items.map((item) => ({ ...item }));
    state.discount = sale.discount;
    state.paymentMethod = sale.paymentMethod;
    state.suspendedSales = state.suspendedSales.filter((item) => item.id !== suspendedId);
    persistSuspendedSales();
    state.message = 'Venta restaurada desde suspendidas';
};

const checkout = () => {
    if (!cart.value.length || total.value <= 0) {
        state.message = 'Agrega productos antes de cobrar';
        return;
    }

    const checkoutTotal = total.value;

    router.post(route('pos.checkout'), {
        items: cart.value.map((item) => ({
            product_id: item.id,
            qty: item.qty,
        })),
        payment_method: state.paymentMethod,
        discount: state.discount,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            cart.value = [];
            state.discount = 0;
            state.message = `Venta cobrada por ${moneyFormatter.value.format(checkoutTotal)} con ${paymentName(state.paymentMethod)}`;
        },
        onError: (errors) => {
            state.message = errors.items ?? 'No se pudo registrar la venta.';
        },
    });
};

const paymentName = (key) => props.paymentMethods.find((method) => method.key === key)?.name ?? key;

const rowSubtotal = (item) => moneyFormatter.value.format(item.qty * item.price);
const money = (value) => moneyFormatter.value.format(Number(value) || 0);

const handleShortcuts = (event) => {
    if (event.ctrlKey && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        searchInput.value?.focus();
        return;
    }

    if (event.ctrlKey && event.key === 'Enter') {
        event.preventDefault();
        checkout();
        return;
    }

    if (event.altKey && event.key.toLowerCase() === 's') {
        event.preventDefault();
        suspendSale();
        return;
    }

    if (event.key === 'Escape') {
        event.preventDefault();
        state.search = '';
        state.message = 'Busqueda limpiada';
    }
};

onMounted(() => {
    loadSuspendedSales();
    window.addEventListener('keydown', handleShortcuts);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleShortcuts);
});
</script>

<template>
    <Head title="POS" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold leading-tight text-slate-900">Punto de venta</h2>
                    <p class="mt-1 text-sm text-slate-500">Operacion rapida para mostrador.</p>
                </div>
                <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">
                    Turno activo
                </span>
            </div>
        </template>

        <div class="grid gap-6 lg:grid-cols-3">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2">
                <div class="mb-4 rounded-xl border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm text-indigo-900">
                    <strong>Venta suspendida:</strong> guarda el carrito actual para retomarlo despues sin perder items.
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-base font-semibold text-slate-900">Venta rapida</h3>
                    <p class="text-xs text-slate-500">{{ terminal.currency }}</p>
                </div>

                <div class="mt-4">
                    <label class="text-xs uppercase tracking-wider text-slate-500">Buscar producto</label>
                    <div class="mt-2 flex gap-2">
                        <input
                            ref="searchInput"
                            v-model="state.search"
                            type="text"
                            class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="Codigo o nombre"
                        />
                    </div>
                </div>

                <div class="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                    <button
                        v-for="product in filteredProducts"
                        :key="product.id"
                        type="button"
                        class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-left transition hover:border-indigo-300 hover:bg-indigo-50"
                        @click="addProduct(product)"
                    >
                        <p class="truncate text-sm font-semibold text-slate-800">{{ product.name }}</p>
                        <p class="text-xs text-slate-500">{{ product.code }} · stock {{ product.stock }}</p>
                        <p class="mt-1 text-xs font-semibold text-indigo-600">{{ money(product.price) }}</p>
                    </button>
                </div>

                <div class="mt-4 overflow-hidden rounded-xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Producto</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Cant.</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Precio</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Subtotal</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500"></th>
                            </tr>
                        </thead>
                        <tbody v-if="cart.length" class="divide-y divide-slate-100">
                            <tr v-for="item in cart" :key="item.id">
                                <td class="px-3 py-2">
                                    <p class="font-medium text-slate-800">{{ item.name }}</p>
                                    <p class="text-xs text-slate-500">{{ item.code }}</p>
                                </td>
                                <td class="px-3 py-2">
                                    <input
                                        :value="item.qty"
                                        type="number"
                                        min="0"
                                        step="0.5"
                                        class="w-20 rounded-lg border-slate-300 text-sm"
                                        @input="updateQty(item, $event.target.value)"
                                    />
                                </td>
                                <td class="px-3 py-2">{{ money(item.price) }}</td>
                                <td class="px-3 py-2">{{ rowSubtotal(item) }}</td>
                                <td class="px-3 py-2">
                                    <button
                                        type="button"
                                        class="rounded-lg border border-rose-200 px-2 py-1 text-xs font-semibold text-rose-600 hover:bg-rose-50"
                                        @click="removeItem(item.id)"
                                    >
                                        Quitar
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                        <tbody v-else>
                            <tr>
                                <td colspan="5" class="px-3 py-6 text-center text-slate-400">No hay items en el carrito</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        @click="suspendSale"
                    >
                        Suspender
                    </button>
                    <button
                        type="button"
                        class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        @click="clearCart"
                    >
                        Limpiar
                    </button>
                    <button
                        type="button"
                        class="rounded-xl bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500"
                        @click="checkout"
                    >
                        Cobrar
                    </button>
                </div>
            </section>

            <aside class="space-y-4">
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="text-base font-semibold text-slate-900">Terminal</h3>
                    <dl class="mt-3 space-y-2 text-sm text-slate-600">
                        <div>
                            <dt class="text-xs uppercase tracking-wider text-slate-500">Comercio</dt>
                            <dd>{{ terminal.tenant }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wider text-slate-500">Sucursal</dt>
                            <dd>{{ terminal.branch }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wider text-slate-500">Operador</dt>
                            <dd>{{ terminal.operator }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h4 class="text-sm font-semibold text-slate-900">Resumen</h4>
                    <div class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between text-slate-600">
                            <span>Subtotal</span>
                            <span>{{ money(subtotal) }}</span>
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs uppercase tracking-wider text-slate-500">Descuento manual</label>
                            <input
                                v-model.number="state.discount"
                                type="number"
                                min="0"
                                class="w-full rounded-lg border-slate-300 text-sm"
                            />
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs uppercase tracking-wider text-slate-500">Medio de pago</label>
                            <select v-model="state.paymentMethod" class="w-full rounded-lg border-slate-300 text-sm">
                                <option v-for="method in paymentMethods" :key="method.key" :value="method.key">
                                    {{ method.name }}
                                </option>
                            </select>
                        </div>
                        <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-bold text-slate-900">
                            <span>Total</span>
                            <span>{{ money(total) }}</span>
                        </div>
                    </div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h4 class="text-sm font-semibold text-slate-900">Estado</h4>
                    <p
                        v-if="page.props.flash?.success || page.props.flash?.error"
                        class="mt-2 rounded-lg px-3 py-2 text-sm"
                        :class="page.props.flash?.error ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700'"
                    >
                        {{ page.props.flash?.error ?? page.props.flash?.success }}
                    </p>
                    <p class="mt-2 text-sm text-slate-600">
                        Ventas suspendidas: <span class="font-semibold text-slate-900">{{ state.suspendedSales.length }}</span>
                    </p>
                    <p v-if="state.message" class="mt-3 rounded-lg bg-slate-100 px-3 py-2 text-sm text-slate-700">
                        {{ state.message }}
                    </p>

                    <div v-if="state.suspendedSales.length" class="mt-3 space-y-2">
                        <button
                            v-for="sale in state.suspendedSales"
                            :key="sale.id"
                            type="button"
                            class="flex w-full items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-left text-sm hover:bg-slate-50"
                            @click="resumeSale(sale.id)"
                        >
                            <span>{{ sale.items.length }} item(s)</span>
                            <span class="font-semibold">{{ money(sale.total) }}</span>
                        </button>
                    </div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h4 class="text-sm font-semibold text-slate-900">Atajos</h4>
                    <p class="mt-1 text-xs text-slate-500">Atajos activos en esta pantalla.</p>
                    <ul class="mt-2 space-y-1 text-sm text-slate-600">
                        <li v-for="shortcut in shortcuts" :key="shortcut">- {{ shortcut }}</li>
                    </ul>
                </section>
            </aside>
        </div>
    </AuthenticatedLayout>
</template>
