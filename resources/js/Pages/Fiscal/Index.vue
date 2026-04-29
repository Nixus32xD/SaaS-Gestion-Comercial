<script setup>
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    configuration: { type: Object, required: true },
    connection: { type: Object, required: true },
    setup: { type: Object, required: true },
    activities: { type: Array, default: () => [] },
    points_of_sale: { type: Array, default: () => [] },
    summary: { type: Object, required: true },
    documents: { type: Array, default: () => [] },
});

const money = (value) => new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 2,
}).format(Number(value) || 0);

const formatCuit = (value) => {
    const digits = String(value || '').replace(/\D/g, '');

    if (digits.length !== 11) {
        return value || '-';
    }

    return `${digits.slice(0, 2)}-${digits.slice(2, 10)}-${digits.slice(10)}`;
};

const summaryCards = computed(() => [
    { key: 'authorized', label: 'Autorizados', value: props.summary.authorized || 0, tone: 'emerald' },
    { key: 'rejected', label: 'Rechazados', value: props.summary.rejected || 0, tone: 'rose' },
    { key: 'uncertain', label: 'Inciertos', value: props.summary.uncertain || 0, tone: 'amber' },
    { key: 'error', label: 'Con error', value: props.summary.error || 0, tone: 'rose' },
    { key: 'processing', label: 'En proceso', value: props.summary.processing || 0, tone: 'cyan' },
]);

const conceptLabel = computed(() => {
    if (Number(props.configuration.concept) === 2) return 'Servicios';
    if (Number(props.configuration.concept) === 3) return 'Productos y servicios';

    return 'Productos';
});

const authorizationModeLabel = computed(() => {
    if (props.configuration.authorization_mode === 'caea') return 'CAEA contingencia';
    if (props.configuration.authorization_mode === 'auto') return 'Automatico';

    return 'CAE normal';
});

const statusLabel = (status) => {
    if (status === 'authorized') return 'Autorizado';
    if (status === 'rejected') return 'Rechazado';
    if (status === 'uncertain') return 'Incierto';
    if (status === 'processing') return 'En proceso';
    if (status === 'error') return 'Error';

    return status || '-';
};

const toneClass = (tone) => {
    if (tone === 'emerald') return 'border-emerald-200/35 bg-emerald-400/15 text-emerald-100';
    if (tone === 'rose') return 'border-rose-200/35 bg-rose-400/15 text-rose-100';
    if (tone === 'amber') return 'border-amber-200/35 bg-amber-400/15 text-amber-100';
    if (tone === 'cyan') return 'border-cyan-200/35 bg-cyan-400/15 text-cyan-100';

    return 'border-slate-200/20 bg-slate-950/35 text-slate-300';
};

const statusClass = (status) => {
    if (status === 'authorized') return toneClass('emerald');
    if (status === 'rejected' || status === 'error') return toneClass('rose');
    if (status === 'uncertain' || status === 'processing') return toneClass('amber');

    return toneClass('slate');
};

const connectionTone = computed(() => (
    props.connection.ok ? 'emerald' : (props.connection.status === 'uncertain' ? 'amber' : 'rose')
));

const setupTone = computed(() => (props.setup.ready ? 'emerald' : 'amber'));
const fiscalCuitLabel = computed(() => formatCuit(props.configuration.fiscal_cuit));

const voucherLabel = (document) => {
    if (!document.point_of_sale || !document.number) {
        return '-';
    }

    const typeLabel = Number(document.cbte_type) === 11 ? 'Factura C' : `Comprobante ${document.cbte_type}`;
    const pointOfSale = String(document.point_of_sale).padStart(5, '0');
    const number = String(document.number).padStart(8, '0');

    return `${typeLabel} ${pointOfSale}-${number}`;
};

const authorizationTypeLabel = (document) => (
    document.authorization_type || (document.cae ? 'CAE' : '-')
);

const authorizationCode = (document) => (
    document.authorization_code || document.cae || '-'
);

const authorizationExpiresAt = (document) => (
    document.authorization_expires_at || document.cae_expires_at || null
);

const compactList = (rows) => {
    if (!rows.length) return '-';

    return rows.map((row) => {
        if (typeof row === 'string' || typeof row === 'number') {
            return row;
        }

        if (row && typeof row === 'object') {
            return row.name || row.description || row.code || row.id || JSON.stringify(row);
        }

        return String(row || '');
    }).join(', ');
};

const valueAt = (row, keys) => {
    for (const key of keys) {
        const value = key.split('.').reduce((carry, segment) => (
            carry && typeof carry === 'object' ? carry[segment] : undefined
        ), row);

        if (value !== undefined && value !== null && value !== '') {
            return value;
        }
    }

    return null;
};

const apiErrorMessage = (row) => {
    if (!row || typeof row !== 'object') return null;

    const error = valueAt(row, ['error']);

    if (typeof error === 'string') {
        return error;
    }

    if (!error || typeof error !== 'object') {
        return null;
    }

    const code = valueAt(error, ['code']);
    const message = valueAt(error, ['message', 'description']);

    if (!code && !message) {
        return null;
    }

    return [code ? `API fiscal ${code}` : 'API fiscal', message].filter(Boolean).join(': ');
};

const formatActivity = (row) => {
    if (typeof row === 'string' || typeof row === 'number') {
        return String(row);
    }

    const code = valueAt(row, ['code', 'id']);
    const name = valueAt(row, ['name', 'description']);

    return [code, name].filter(Boolean).join(' - ') || JSON.stringify(row);
};

const formatPointOfSale = (row) => {
    if (typeof row === 'string' || typeof row === 'number') {
        return String(row);
    }

    const number = valueAt(row, ['number', 'point_of_sale', 'id']);
    const type = valueAt(row, ['type', 'emission_type', 'description', 'name']);
    const blocked = valueAt(row, ['blocked']);
    const status = blocked === true ? 'bloqueado' : null;

    return [
        number ? `PV ${number}` : null,
        type,
        status,
    ].filter(Boolean).join(' - ') || JSON.stringify(row);
};

const displayRows = (rows, formatter) => {
    return (Array.isArray(rows) ? rows : []).reduce((payload, row) => {
        const error = apiErrorMessage(row);

        if (error) {
            payload.errors.push(error);
        } else {
            payload.items.push(formatter(row));
        }

        return payload;
    }, { items: [], errors: [] });
};

const activityRows = computed(() => displayRows(props.activities, formatActivity));
const pointOfSaleRows = computed(() => displayRows(props.points_of_sale, formatPointOfSale));

const retryDocument = (document) => {
    if (!window.confirm('Reintentar emision fiscal para esta venta?')) return;

    router.post(route('sales.fiscal-documents.store', document.sale_id), {}, {
        preserveScroll: true,
    });
};

const reconcileDocument = (document) => {
    router.post(route('sales.fiscal-documents.reconcile', {
        sale: document.sale_id,
        saleFiscalDocument: document.id,
    }), {}, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Facturacion electronica" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold text-slate-100">Facturacion electronica</h2>
                    <p class="mt-1 text-sm text-slate-300/80">Configuracion fiscal, estado de API y comprobantes emitidos.</p>
                </div>
                <Link :href="route('sales.index')" class="text-sm font-semibold text-slate-300 hover:text-slate-100">Ventas</Link>
            </div>
        </template>

        <div class="grid gap-6">
            <section class="grid gap-4 xl:grid-cols-5">
                <article v-for="card in summaryCards" :key="card.key" class="rounded-xl border bg-slate-900/45 p-4 shadow-sm backdrop-blur" :class="toneClass(card.tone)">
                    <p class="text-xs uppercase tracking-[0.2em] opacity-80">{{ card.label }}</p>
                    <p class="mt-3 text-2xl font-semibold">{{ card.value }}</p>
                </article>
            </section>

            <section class="grid gap-6 xl:grid-cols-2">
                <article class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-sm backdrop-blur">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-slate-100">Conexion fiscal</h3>
                            <p class="mt-1 text-sm text-slate-300/80">{{ connection.message || 'API fiscal configurada para este comercio.' }}</p>
                        </div>
                        <span class="rounded-full border px-3 py-1 text-xs font-semibold" :class="toneClass(connectionTone)">
                            {{ connection.status_label }}
                        </span>
                    </div>

                    <dl class="mt-4 grid gap-3 text-sm text-slate-300">
                        <div>
                            <dt class="text-xs uppercase tracking-[0.18em] text-slate-400">Empresa fiscal</dt>
                            <dd class="mt-1 font-semibold text-slate-100">{{ configuration.external_business_id }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-[0.18em] text-slate-400">CUIT fiscal</dt>
                            <dd class="mt-1 font-semibold text-slate-100">{{ fiscalCuitLabel }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-[0.18em] text-slate-400">Puntos de venta API</dt>
                            <dd class="mt-2">
                                <ul v-if="pointOfSaleRows.items.length" class="flex flex-wrap gap-2">
                                    <li
                                        v-for="item in pointOfSaleRows.items"
                                        :key="item"
                                        class="rounded-lg border border-cyan-100/20 bg-slate-950/35 px-3 py-1.5 text-xs font-semibold text-slate-100"
                                    >
                                        {{ item }}
                                    </li>
                                </ul>
                                <p v-else-if="!pointOfSaleRows.errors.length" class="text-slate-400">Sin puntos de venta informados.</p>
                                <p
                                    v-for="error in pointOfSaleRows.errors"
                                    :key="error"
                                    class="mt-2 rounded-lg border border-amber-200/35 bg-amber-400/10 px-3 py-2 text-xs font-semibold text-amber-100"
                                >
                                    {{ error }}
                                </p>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-[0.18em] text-slate-400">Actividades API</dt>
                            <dd class="mt-2">
                                <ul v-if="activityRows.items.length" class="flex flex-wrap gap-2">
                                    <li
                                        v-for="item in activityRows.items"
                                        :key="item"
                                        class="rounded-lg border border-cyan-100/20 bg-slate-950/35 px-3 py-1.5 text-xs font-semibold text-slate-100"
                                    >
                                        {{ item }}
                                    </li>
                                </ul>
                                <p v-else-if="!activityRows.errors.length" class="text-slate-400">Sin actividades informadas.</p>
                                <p
                                    v-for="error in activityRows.errors"
                                    :key="error"
                                    class="mt-2 rounded-lg border border-amber-200/35 bg-amber-400/10 px-3 py-2 text-xs font-semibold text-amber-100"
                                >
                                    {{ error }}
                                </p>
                            </dd>
                        </div>
                    </dl>
                </article>

                <article class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-sm backdrop-blur">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-slate-100">Setup fiscal</h3>
                            <p class="mt-1 text-sm text-slate-300/80">Estado operativo informado por la API fiscal externa.</p>
                        </div>
                        <span class="rounded-full border px-3 py-1 text-xs font-semibold" :class="toneClass(setupTone)">
                            {{ setup.status_label }}
                        </span>
                    </div>

                    <dl class="mt-4 grid gap-3 text-sm text-slate-300">
                        <div>
                            <dt class="text-xs uppercase tracking-[0.18em] text-slate-400">Estado operativo</dt>
                            <dd class="mt-1 font-semibold text-slate-100">{{ setup.ready ? 'Listo' : 'Revisar setup' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-[0.18em] text-slate-400">Ambiente fiscal</dt>
                            <dd class="mt-1 font-semibold text-slate-100">{{ setup.environment || '-' }}</dd>
                        </div>
                        <div v-if="setup.message">
                            <dt class="text-xs uppercase tracking-[0.18em] text-slate-400">Detalle</dt>
                            <dd class="mt-1 font-semibold text-slate-100">{{ setup.message }}</dd>
                        </div>
                    </dl>
                </article>
            </section>

            <section class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-sm backdrop-blur">
                <h3 class="text-base font-semibold text-slate-100">Configuracion del comercio</h3>

                <dl class="mt-4 grid gap-3 text-sm text-slate-300 md:grid-cols-2 xl:grid-cols-3">
                    <div>
                        <dt class="text-xs uppercase tracking-[0.18em] text-slate-400">CUIT fiscal</dt>
                        <dd class="mt-1 font-semibold text-slate-100">{{ fiscalCuitLabel }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-[0.18em] text-slate-400">Punto de venta</dt>
                        <dd class="mt-1 font-semibold text-slate-100">{{ configuration.point_of_sale }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-[0.18em] text-slate-400">Documento</dt>
                        <dd class="mt-1 font-semibold text-slate-100">{{ configuration.document_type }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-[0.18em] text-slate-400">Tipo fiscal</dt>
                        <dd class="mt-1 font-semibold text-slate-100">{{ configuration.cbte_type }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-[0.18em] text-slate-400">Concepto</dt>
                        <dd class="mt-1 font-semibold text-slate-100">{{ conceptLabel }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-[0.18em] text-slate-400">Modo autorizacion</dt>
                        <dd class="mt-1 font-semibold text-slate-100">{{ authorizationModeLabel }}</dd>
                    </div>
                    <div class="md:col-span-2">
                        <dt class="text-xs uppercase tracking-[0.18em] text-slate-400">Actividades configuradas</dt>
                        <dd class="mt-1 font-semibold text-slate-100">{{ compactList(configuration.activities || []) }}</dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-sm backdrop-blur">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-slate-100">Comprobantes recientes</h3>
                        <p class="mt-1 text-sm text-slate-300/80">Ultimos 25 intentos fiscales registrados desde ventas.</p>
                    </div>
                    <Link :href="route('sales.index')" class="rounded-lg border border-cyan-100/25 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70">
                        Ir a ventas
                    </Link>
                </div>

                <div class="mt-4 overflow-x-auto rounded-xl border border-cyan-100/20 app-table-wrap">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-950/35">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Venta</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Estado</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Comprobante</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Autorizacion</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Total</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300/80">Acciones</th>
                            </tr>
                        </thead>
                        <tbody v-if="documents.length" class="divide-y divide-slate-100">
                            <tr v-for="document in documents" :key="document.id">
                                <td class="px-3 py-2">
                                    <Link :href="route('sales.show', document.sale_id)" class="font-semibold text-cyan-100 hover:text-cyan-50">
                                        {{ document.sale_number || `Venta #${document.sale_id}` }}
                                    </Link>
                                    <p class="mt-1 text-xs text-slate-400">{{ document.sold_at || '-' }} - intento {{ document.attempt_number }}</p>
                                </td>
                                <td class="px-3 py-2">
                                    <span class="rounded-full border px-2 py-1 text-xs font-semibold" :class="statusClass(document.status)">
                                        {{ statusLabel(document.status) }}
                                    </span>
                                    <p v-if="document.error_message" class="mt-2 max-w-xs text-xs text-rose-200">{{ document.error_message }}</p>
                                    <p v-if="document.error_action" class="mt-1 max-w-xs text-xs text-slate-400">Accion: {{ document.error_action }}</p>
                                </td>
                                <td class="px-3 py-2 text-slate-200">{{ voucherLabel(document) }}</td>
                                <td class="px-3 py-2 text-slate-200">
                                    <span>{{ authorizationTypeLabel(document) }} {{ authorizationCode(document) }}</span>
                                    <p v-if="authorizationExpiresAt(document)" class="mt-1 text-xs text-slate-400">Vence {{ authorizationExpiresAt(document) }}</p>
                                    <p v-if="document.caea_report_status" class="mt-1 text-xs text-amber-100">Reporte CAEA: {{ document.caea_report_status }}</p>
                                </td>
                                <td class="px-3 py-2 text-slate-200">{{ money(document.sale_total) }}</td>
                                <td class="px-3 py-2">
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            v-if="document.can_reconcile"
                                            type="button"
                                            class="rounded-lg border border-amber-200/45 px-3 py-1.5 text-xs font-semibold text-amber-100 hover:bg-amber-400/10"
                                            @click="reconcileDocument(document)"
                                        >
                                            Conciliar
                                        </button>
                                        <button
                                            v-if="document.can_retry"
                                            type="button"
                                            class="rounded-lg border border-emerald-200/45 px-3 py-1.5 text-xs font-semibold text-emerald-100 hover:bg-emerald-400/10"
                                            @click="retryDocument(document)"
                                        >
                                            Reintentar
                                        </button>
                                        <Link :href="route('sales.show', document.sale_id)" class="rounded-lg border border-cyan-100/25 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-800/70">
                                            Ver venta
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                        <tbody v-else>
                            <tr>
                                <td colspan="6" class="px-3 py-6 text-center text-slate-400">Todavia no hay comprobantes fiscales emitidos.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
