<script setup>
import AppPanel from '@/Components/AppPanel.vue';
import MetricCard from '@/Components/MetricCard.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps({
    suppliers: { type: Object, required: true },
    filters: { type: Object, default: () => ({ search: '' }) },
});

const state = reactive({ search: props.filters.search || '' });

const visibleSuppliers = computed(() => props.suppliers.data || []);
const suppliersWithPhone = computed(() => visibleSuppliers.value.filter((supplier) => supplier.phone).length);
const suppliersWithEmail = computed(() => visibleSuppliers.value.filter((supplier) => supplier.email).length);

const filter = () => {
    router.get(route('suppliers.index'), { search: state.search }, { preserveState: true, replace: true });
};

const clearFilters = () => {
    state.search = '';
    filter();
};
</script>

<template>
    <Head title="Proveedores" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold text-slate-100">Proveedores</h2>
                    <p class="mt-1 text-sm text-slate-300/80">Gestion de proveedores del comercio.</p>
                </div>
                <Link :href="route('suppliers.create')" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                    Nuevo proveedor
                </Link>
            </div>
        </template>

        <div class="grid gap-6">
            <section class="app-kpi-grid">
                <MetricCard label="Proveedores visibles" :value="suppliers.total" hint="Resultado total con la busqueda actual." />
                <MetricCard label="Con telefono" :value="suppliersWithPhone" hint="Util para contacto rapido de compras." />
                <MetricCard label="Con email" :value="suppliersWithEmail" hint="Listos para pedidos o recepcion de facturas." />
            </section>

            <AppPanel title="Busqueda de proveedores" subtitle="Encuentra rapido al proveedor correcto por nombre o dato de contacto.">
                <template #actions>
                    <button type="button" class="rounded-lg border border-rose-300/35 px-3 py-2 text-sm font-semibold text-rose-100 hover:bg-rose-400/20" @click="clearFilters">Limpiar</button>
                </template>

                <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto]">
                    <input
                        v-model="state.search"
                        type="text"
                        class="rounded-xl text-sm"
                        placeholder="Buscar por nombre, contacto, telefono o email"
                        @keyup.enter="filter"
                    >
                    <button type="button" class="rounded-lg border border-cyan-100/25 px-3 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70" @click="filter">Buscar</button>
                </div>

                <div v-if="state.search" class="mt-4 app-chip-row">
                    <StatusBadge tone="info" :label="`Busqueda: ${state.search}`" />
                </div>
            </AppPanel>

            <AppPanel title="Listado de proveedores" subtitle="Prioriza nombre, contacto y vias de comunicacion para compras y reposicion.">
                <div class="grid gap-3 md:hidden">
                    <article v-for="supplier in suppliers.data" :key="supplier.id" class="rounded-xl border border-cyan-100/20 bg-slate-950/35 p-4 text-sm text-slate-300">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-semibold text-slate-100">{{ supplier.name }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ supplier.contact_name || 'Sin contacto' }}</p>
                            </div>
                            <Link :href="route('suppliers.edit', supplier.id)" class="shrink-0 rounded-lg border border-cyan-100/25 px-3 py-1 text-xs font-semibold text-slate-300 hover:bg-slate-800/70">Editar</Link>
                        </div>

                        <div class="mt-3 app-chip-row">
                            <StatusBadge :tone="supplier.phone ? 'success' : 'neutral'" size="sm" :label="supplier.phone || 'Sin telefono'" />
                            <StatusBadge :tone="supplier.email ? 'info' : 'neutral'" size="sm" :label="supplier.email || 'Sin email'" />
                        </div>
                    </article>
                </div>

                <div class="hidden overflow-x-auto rounded-xl border border-cyan-100/20 app-table-wrap md:block">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-950/35">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Proveedor</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Contacto</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Canales</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80"></th>
                            </tr>
                        </thead>
                        <tbody v-if="suppliers.data.length" class="divide-y divide-slate-100">
                            <tr v-for="supplier in suppliers.data" :key="supplier.id">
                                <td class="px-3 py-2 font-semibold text-slate-100">{{ supplier.name }}</td>
                                <td class="px-3 py-2">{{ supplier.contact_name || '-' }}</td>
                                <td class="px-3 py-2">
                                    <div class="app-chip-row">
                                        <StatusBadge :tone="supplier.phone ? 'success' : 'neutral'" size="sm" :label="supplier.phone || 'Sin telefono'" />
                                        <StatusBadge :tone="supplier.email ? 'info' : 'neutral'" size="sm" :label="supplier.email || 'Sin email'" />
                                    </div>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <Link :href="route('suppliers.edit', supplier.id)" class="rounded-lg border border-cyan-100/25 px-3 py-1 text-xs font-semibold text-slate-300 hover:bg-slate-800/70">Editar</Link>
                                </td>
                            </tr>
                        </tbody>
                        <tbody v-else>
                            <tr>
                                <td colspan="4" class="px-3 py-6 text-center text-slate-400">No hay proveedores.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="suppliers.links?.length" class="mt-4 flex flex-wrap gap-2">
                    <component
                        :is="link.url ? Link : 'span'"
                        v-for="(link, index) in suppliers.links"
                        :key="index"
                        :href="link.url || undefined"
                        class="rounded-lg border px-3 py-1 text-xs"
                        :class="link.active ? 'border-cyan-300/50 bg-cyan-400/15 text-cyan-100' : 'border-cyan-100/25 text-slate-300'"
                        v-html="link.label"
                    />
                </div>
            </AppPanel>
        </div>
    </AuthenticatedLayout>
</template>
