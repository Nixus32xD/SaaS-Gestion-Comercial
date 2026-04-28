<script setup>
import AppPanel from '@/Components/AppPanel.vue';
import MetricCard from '@/Components/MetricCard.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    purchase: { type: Object, required: true },
});

const money = (value) => new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 2,
}).format(Number(value) || 0);

const itemsCount = computed(() => props.purchase.items?.length || 0);
const itemsWithExpiry = computed(() => (props.purchase.items || []).filter((item) => item.expires_at).length);
const itemsWithoutExpiry = computed(() => itemsCount.value - itemsWithExpiry.value);
</script>

<template>
    <Head :title="purchase.purchase_number || `Compra #${purchase.id}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold text-slate-100">Detalle de compra</h2>
                    <p class="mt-1 text-sm text-slate-300/80">{{ purchase.purchase_number || `Compra #${purchase.id}` }} · {{ purchase.purchased_at }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="route('purchases.create')" class="rounded-lg border border-cyan-100/25 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70">Nueva compra</Link>
                    <Link :href="route('purchases.index')" class="rounded-lg border border-cyan-100/25 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70">Volver</Link>
                </div>
            </div>
        </template>

        <div class="grid gap-6">
            <section class="app-kpi-grid">
                <MetricCard label="Total invertido" :value="money(purchase.total)" hint="Importe final de la compra." :tone="purchase.total > 0 ? 'accent' : 'default'" />
                <MetricCard label="Items cargados" :value="itemsCount" hint="Cantidad de lineas registradas en la compra." />
                <MetricCard label="Con vencimiento" :value="itemsWithExpiry" :hint="`${itemsWithoutExpiry} items sin fecha de vencimiento.`" :tone="itemsWithExpiry > 0 ? 'warning' : 'default'" />
                <MetricCard label="Proveedor" :value="purchase.supplier || 'Sin proveedor'" hint="Referencia del proveedor asociado." />
            </section>

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_22rem]">
                <div class="grid gap-6">
                    <AppPanel title="Resumen de compra" subtitle="Datos principales para control de reposicion y costos.">
                        <div class="grid gap-3 md:grid-cols-2 text-sm text-slate-300">
                            <div class="app-subsection">
                                <p class="app-section-title">Operacion</p>
                                <p class="mt-2">Usuario: <strong class="text-slate-100">{{ purchase.user || '-' }}</strong></p>
                                <p class="mt-1">Proveedor: <strong class="text-slate-100">{{ purchase.supplier || '-' }}</strong></p>
                                <p class="mt-1">Fecha: <strong class="text-slate-100">{{ purchase.purchased_at || '-' }}</strong></p>
                            </div>
                            <div class="app-subsection">
                                <p class="app-section-title">Importes</p>
                                <p class="mt-2">Subtotal: <strong class="text-slate-100">{{ money(purchase.subtotal) }}</strong></p>
                                <p class="mt-1">Total: <strong class="text-slate-100">{{ money(purchase.total) }}</strong></p>
                            </div>
                        </div>

                        <div class="mt-4 app-subsection">
                            <p class="app-section-title">Notas</p>
                            <p class="mt-2 text-sm text-slate-300">{{ purchase.notes || 'Sin observaciones de compra.' }}</p>
                        </div>
                    </AppPanel>

                    <AppPanel title="Items y lotes" subtitle="Detalle de cantidades, costos y vencimientos para control rapido.">
                        <div class="grid gap-3 md:hidden">
                            <article v-for="item in purchase.items" :key="item.id" class="rounded-xl border border-cyan-100/20 bg-slate-950/35 p-4 text-sm text-slate-300">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-semibold text-slate-100">{{ item.product_name }}</p>
                                    <StatusBadge :tone="item.expires_at ? 'warning' : 'neutral'" size="sm" :label="item.expires_at ? 'Con vencimiento' : 'Sin vencimiento'" />
                                </div>
                                <p class="mt-2 text-xs text-slate-400">{{ item.quantity }} {{ item.quantity_label }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ money(item.unit_cost) }} {{ item.price_label }}</p>
                                <p class="mt-1 text-xs text-slate-400">Vence: {{ item.expires_at || '-' }}</p>
                                <p class="mt-3 text-sm">Subtotal: <strong class="text-slate-100">{{ money(item.subtotal) }}</strong></p>
                            </article>
                        </div>

                        <div class="hidden overflow-x-auto rounded-xl border border-cyan-100/20 app-table-wrap md:block">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-950/35">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium text-slate-300/80">Producto</th>
                                        <th class="px-3 py-2 text-left font-medium text-slate-300/80">Cantidad</th>
                                        <th class="px-3 py-2 text-left font-medium text-slate-300/80">Costo</th>
                                        <th class="px-3 py-2 text-left font-medium text-slate-300/80">Vencimiento</th>
                                        <th class="px-3 py-2 text-left font-medium text-slate-300/80">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="item in purchase.items" :key="item.id">
                                        <td class="px-3 py-2 font-semibold text-slate-100">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span>{{ item.product_name }}</span>
                                                <StatusBadge :tone="item.expires_at ? 'warning' : 'neutral'" size="sm" :label="item.expires_at ? 'Con vencimiento' : 'Sin vencimiento'" />
                                            </div>
                                        </td>
                                        <td class="px-3 py-2">{{ item.quantity }} <span class="text-xs text-slate-400">{{ item.quantity_label }}</span></td>
                                        <td class="px-3 py-2">{{ money(item.unit_cost) }} <span class="text-xs text-slate-400">{{ item.price_label }}</span></td>
                                        <td class="px-3 py-2">{{ item.expires_at || '-' }}</td>
                                        <td class="px-3 py-2">{{ money(item.subtotal) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </AppPanel>
                </div>

                <aside class="app-sticky-column">
                    <AppPanel title="Lectura rapida" :tone="itemsWithExpiry > 0 ? 'warning' : 'default'" subtitle="Resumen corto para control de reposicion y mercaderia.">
                        <div class="space-y-3 text-sm text-slate-300">
                            <div class="flex items-center justify-between gap-3">
                                <span>Proveedor</span>
                                <span class="text-right font-semibold text-slate-100">{{ purchase.supplier || 'Sin proveedor' }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <span>Total</span>
                                <span class="font-semibold text-slate-100">{{ money(purchase.total) }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <span>Items</span>
                                <span class="font-semibold text-slate-100">{{ itemsCount }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <span>Con vencimiento</span>
                                <span class="font-semibold text-slate-100">{{ itemsWithExpiry }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <span>Sin vencimiento</span>
                                <span class="font-semibold text-slate-100">{{ itemsWithoutExpiry }}</span>
                            </div>
                        </div>
                    </AppPanel>
                </aside>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
