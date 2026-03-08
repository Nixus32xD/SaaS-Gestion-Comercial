<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    terminal: {
        type: Object,
        required: true,
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
</script>

<template>
    <Head title="POS" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold leading-tight text-slate-900">Punto de Venta</h2>
                    <p class="mt-1 text-sm text-slate-500">Flujo rapido para mostrador.</p>
                </div>
                <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">
                    Turno activo
                </span>
            </div>
        </template>

        <div class="grid gap-6 lg:grid-cols-3">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-semibold text-slate-900">Venta rapida</h3>
                    <p class="text-xs text-slate-500">{{ terminal.currency }}</p>
                </div>
                <div class="mt-4">
                    <label class="text-xs uppercase tracking-wider text-slate-500">Buscar producto</label>
                    <div class="mt-2 flex gap-2">
                        <input
                            type="text"
                            class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="Codigo, nombre o codigo de barras"
                        />
                        <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600">
                            Agregar
                        </button>
                    </div>
                </div>

                <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200 app-table-wrap">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Producto</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Cant.</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Precio</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-500">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="4" class="px-3 py-6 text-center text-slate-400">No hay items en el carrito</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                    <button class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Suspender
                    </button>
                    <button class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Limpiar
                    </button>
                    <button class="rounded-xl bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-500">
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
                            <span>0.00</span>
                        </div>
                        <div class="flex justify-between text-slate-600">
                            <span>Descuentos</span>
                            <span>0.00</span>
                        </div>
                        <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-bold text-slate-900">
                            <span>Total</span>
                            <span>0.00</span>
                        </div>
                    </div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h4 class="text-sm font-semibold text-slate-900">Medios de pago</h4>
                    <ul class="mt-2 space-y-1 text-sm text-slate-600">
                        <li v-for="method in paymentMethods" :key="method.key">- {{ method.name }}</li>
                    </ul>

                    <h4 class="mt-4 text-sm font-semibold text-cyan-100">Atajos</h4>
                    <ul class="mt-2 space-y-1 text-sm text-slate-200">
                        <li v-for="shortcut in shortcuts" :key="shortcut">- {{ shortcut }}</li>
                    </ul>
                </section>
            </aside>
        </div>
    </AuthenticatedLayout>
</template>
