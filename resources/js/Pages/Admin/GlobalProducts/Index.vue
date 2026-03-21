<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    stats: { type: Object, required: true },
    recent_global_products: { type: Array, default: () => [] },
    last_sync_summary: { type: Object, default: null },
});

const form = useForm({});

const syncCatalog = () => {
    form.post(route('admin.global-products.sync'));
};
</script>

<template>
    <Head title="Catalogo global" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold text-slate-100">Catalogo global de productos</h2>
                    <p class="mt-1 text-sm text-slate-300/80">Base reutilizable para acelerar altas locales sin romper el esquema por comercio.</p>
                </div>
                <button
                    type="button"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-60"
                    :disabled="form.processing"
                    @click="syncCatalog"
                >
                    Sincronizar productos al catalogo global
                </button>
            </div>
        </template>

        <div class="grid gap-6">
            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-sm backdrop-blur">
                    <p class="text-xs uppercase tracking-wider text-cyan-100/70">Globales totales</p>
                    <p class="mt-2 text-3xl font-bold text-slate-100">{{ stats.total }}</p>
                </article>
                <article class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-sm backdrop-blur">
                    <p class="text-xs uppercase tracking-wider text-cyan-100/70">Con identificador</p>
                    <p class="mt-2 text-3xl font-bold text-slate-100">{{ stats.with_identifier }}</p>
                </article>
                <article class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-sm backdrop-blur">
                    <p class="text-xs uppercase tracking-wider text-cyan-100/70">Sin categoria</p>
                    <p class="mt-2 text-3xl font-bold text-slate-100">{{ stats.without_category }}</p>
                </article>
                <article class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-sm backdrop-blur">
                    <p class="text-xs uppercase tracking-wider text-cyan-100/70">Locales vinculados</p>
                    <p class="mt-2 text-3xl font-bold text-slate-100">{{ stats.linked_local_products }}</p>
                </article>
            </section>

            <section class="grid gap-4 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]">
                <article class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-sm backdrop-blur">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-base font-semibold text-slate-100">Sincronizacion manual</h3>
                            <p class="mt-1 text-sm text-slate-300/80">Recorre productos de todos los comercios, evita duplicados y vincula los locales que todavia no tengan referencia global.</p>
                        </div>
                        <span class="rounded-full bg-emerald-400/15 px-3 py-1 text-xs font-semibold text-emerald-100">Idempotente</span>
                    </div>

                    <div class="mt-4 rounded-xl border border-cyan-100/20 bg-slate-950/35 p-4 text-sm text-slate-300">
                        <p>Prioridad de matching: barcode exacto y SKU exacto. Solo entran al catalogo global productos que tengan al menos uno de esos identificadores.</p>
                        <p class="mt-2">La accion no toca precios, stock ni datos operativos del comercio.</p>
                    </div>

                    <div v-if="last_sync_summary" class="mt-4 rounded-xl border border-cyan-100/20 bg-slate-950/35 p-4">
                        <h4 class="text-sm font-semibold text-slate-100">Ultimo resultado</h4>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            <div class="rounded-lg border border-cyan-100/15 bg-slate-900/35 px-3 py-2">
                                <p class="text-xs uppercase tracking-wide text-slate-400">Analizados</p>
                                <p class="mt-1 text-lg font-semibold text-slate-100">{{ last_sync_summary.analyzed }}</p>
                            </div>
                            <div class="rounded-lg border border-cyan-100/15 bg-slate-900/35 px-3 py-2">
                                <p class="text-xs uppercase tracking-wide text-slate-400">Creados global</p>
                                <p class="mt-1 text-lg font-semibold text-slate-100">{{ last_sync_summary.created }}</p>
                            </div>
                            <div class="rounded-lg border border-cyan-100/15 bg-slate-900/35 px-3 py-2">
                                <p class="text-xs uppercase tracking-wide text-slate-400">Ya existentes</p>
                                <p class="mt-1 text-lg font-semibold text-slate-100">{{ last_sync_summary.existing }}</p>
                            </div>
                            <div class="rounded-lg border border-cyan-100/15 bg-slate-900/35 px-3 py-2">
                                <p class="text-xs uppercase tracking-wide text-slate-400">Omitidos</p>
                                <p class="mt-1 text-lg font-semibold text-slate-100">{{ last_sync_summary.skipped }}</p>
                            </div>
                            <div class="rounded-lg border border-cyan-100/15 bg-slate-900/35 px-3 py-2">
                                <p class="text-xs uppercase tracking-wide text-slate-400">Locales vinculados</p>
                                <p class="mt-1 text-lg font-semibold text-slate-100">{{ last_sync_summary.linked }}</p>
                            </div>
                            <div class="rounded-lg border border-cyan-100/15 bg-slate-900/35 px-3 py-2">
                                <p class="text-xs uppercase tracking-wide text-slate-400">Conflictos</p>
                                <p class="mt-1 text-lg font-semibold text-slate-100">{{ last_sync_summary.conflicts }}</p>
                            </div>
                            <div class="rounded-lg border border-cyan-100/15 bg-slate-900/35 px-3 py-2">
                                <p class="text-xs uppercase tracking-wide text-slate-400">Errores</p>
                                <p class="mt-1 text-lg font-semibold text-slate-100">{{ last_sync_summary.error_count }}</p>
                            </div>
                        </div>

                        <div v-if="last_sync_summary.errors?.length" class="mt-4 rounded-lg border border-amber-200/30 bg-amber-300/10 p-4 text-sm text-amber-100">
                            <p class="font-semibold">Conflictos y errores detectados</p>
                            <ul class="mt-2 space-y-2">
                                <li v-for="(error, index) in last_sync_summary.errors" :key="`${index}-${error}`">{{ error }}</li>
                            </ul>
                        </div>
                    </div>
                </article>

                <article class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-sm backdrop-blur">
                    <h3 class="text-base font-semibold text-slate-100">Preview reciente</h3>
                    <div class="mt-4 rounded-xl border border-cyan-100/20 bg-slate-950/35">
                        <ul v-if="recent_global_products.length" class="divide-y divide-slate-100/10 text-sm">
                            <li v-for="product in recent_global_products" :key="product.id" class="px-4 py-3">
                                <p class="font-semibold text-slate-100">{{ product.name }}</p>
                                <p class="mt-1 text-xs text-slate-300/80">Barcode: {{ product.barcode || 'sin barcode' }}</p>
                                <p class="text-xs text-slate-300/80">SKU: {{ product.sku || 'sin sku' }}</p>
                                <p class="text-xs text-slate-300/80">Categoria: {{ product.category || 'sin categoria' }}</p>
                                <p class="text-xs text-slate-500">Actualizado: {{ product.updated_at || '-' }}</p>
                            </li>
                        </ul>
                        <p v-else class="px-4 py-5 text-sm text-slate-400">Todavia no hay productos globales cargados.</p>
                    </div>
                </article>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
