<script setup>
import AppPanel from '@/Components/AppPanel.vue';
import MetricCard from '@/Components/MetricCard.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    product: { type: Object, required: true },
    corrections: { type: Object, required: true },
});

const money = (value) => new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 2,
}).format(Number(value) || 0);

const fieldLabel = (value) => {
    if (value === 'codigo') return 'Codigo';
    if (value === 'vencimiento') return 'Vencimiento';
    if (value === 'costo') return 'Costo';
    return value;
};
</script>

<template>
    <Head :title="`Correcciones de ${product.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-slate-100">Historial de correcciones</h2>
                    <p class="mt-1 text-sm text-slate-300/80">{{ product.name }} · revision de cambios manuales sobre lotes.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="route('products.edit', product.id)" class="rounded-lg border border-cyan-100/25 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70">
                        Volver al producto
                    </Link>
                    <Link :href="route('products.index')" class="rounded-lg border border-cyan-100/25 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70">
                        Productos
                    </Link>
                </div>
            </div>
        </template>

        <div class="grid gap-6">
            <section class="app-kpi-grid">
                <MetricCard label="Correcciones" :value="product.batch_corrections_count" hint="Ajustes manuales registrados sobre lotes." />
                <MetricCard label="Lotes revisados" :value="new Set((corrections.data || []).map((item) => item.batch_id)).size" hint="Cantidad de lotes con cambios en esta pagina." />
                <MetricCard label="Stock actual" :value="product.stock" hint="Stock total del producto al momento de revisar." />
                <MetricCard label="Registros visibles" :value="corrections.data?.length || 0" hint="Pagina actual para control rapido." />
            </section>

            <AppPanel title="Revision de correcciones" subtitle="Sirve para controlar quien cambio un lote, que campo toco y con que valor quedo.">
                <div v-if="corrections.data.length" class="grid gap-4">
                    <article v-for="correction in corrections.data" :key="correction.id" class="rounded-2xl border border-cyan-100/20 bg-slate-950/35 p-4 text-sm text-slate-300">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <p class="font-semibold text-slate-100">{{ correction.batch_code }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ correction.created_at || '-' }} · {{ correction.corrector || 'Sin usuario' }}</p>
                            </div>
                            <div class="app-chip-row">
                                <StatusBadge v-for="field in correction.changed_fields" :key="field" tone="info" size="sm" :label="fieldLabel(field)" />
                            </div>
                        </div>

                        <div class="mt-4 grid gap-3 md:grid-cols-3">
                            <div class="rounded-xl border border-cyan-100/10 bg-slate-900/45 p-3">
                                <p class="text-xs uppercase tracking-[0.16em] text-cyan-100/70">Codigo</p>
                                <p class="mt-2 text-xs text-slate-400">Antes</p>
                                <p class="font-semibold text-slate-100">{{ correction.previous_batch_code || '-' }}</p>
                                <p class="mt-2 text-xs text-slate-400">Despues</p>
                                <p class="font-semibold text-slate-100">{{ correction.new_batch_code || '-' }}</p>
                            </div>

                            <div class="rounded-xl border border-cyan-100/10 bg-slate-900/45 p-3">
                                <p class="text-xs uppercase tracking-[0.16em] text-cyan-100/70">Vencimiento</p>
                                <p class="mt-2 text-xs text-slate-400">Antes</p>
                                <p class="font-semibold text-slate-100">{{ correction.previous_expires_at || 'Sin vencimiento' }}</p>
                                <p class="mt-2 text-xs text-slate-400">Despues</p>
                                <p class="font-semibold text-slate-100">{{ correction.new_expires_at || 'Sin vencimiento' }}</p>
                            </div>

                            <div class="rounded-xl border border-cyan-100/10 bg-slate-900/45 p-3">
                                <p class="text-xs uppercase tracking-[0.16em] text-cyan-100/70">Costo</p>
                                <p class="mt-2 text-xs text-slate-400">Antes</p>
                                <p class="font-semibold text-slate-100">{{ correction.previous_unit_cost !== null ? money(correction.previous_unit_cost) : '-' }}</p>
                                <p class="mt-2 text-xs text-slate-400">Despues</p>
                                <p class="font-semibold text-slate-100">{{ correction.new_unit_cost !== null ? money(correction.new_unit_cost) : '-' }}</p>
                            </div>
                        </div>

                        <div class="mt-4 rounded-xl border border-cyan-100/10 bg-slate-900/45 p-3">
                            <p class="text-xs uppercase tracking-[0.16em] text-cyan-100/70">Motivo</p>
                            <p class="mt-2 text-sm text-slate-300">{{ correction.reason || 'Sin motivo registrado.' }}</p>
                        </div>
                    </article>
                </div>
                <p v-else class="text-sm text-slate-400">Este producto todavia no tiene correcciones manuales registradas.</p>

                <div v-if="corrections.links?.length" class="mt-4 flex flex-wrap gap-2">
                    <component
                        :is="link.url ? Link : 'span'"
                        v-for="(link, index) in corrections.links"
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
