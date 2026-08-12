<script setup>
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    configuration: { type: Object, required: true },
    connection: { type: Object, required: true },
    setup: { type: Object, required: true },
    activities: { type: Array, default: () => [] },
    points_of_sale: { type: Array, default: () => [] },
    diagnostics: { type: Object, default: () => ({}) },
    iva_sales_book: { type: Object, default: () => ({}) },
    summary: { type: Object, required: true },
    documents: { type: Array, default: () => [] },
    can_manage_credentials: { type: Boolean, default: false },
    credential_onboarding: { type: Object, default: () => ({}) },
});

const sanitizedBusinessId = String(props.configuration.external_business_id || 'empresa')
    .replace(/[^A-Za-z0-9._-]/g, '-')
    .replace(/-+/g, '-')
    .replace(/^-|-$/g, '');
const defaultKeyName = `${sanitizedBusinessId || 'empresa'}.key`;
const setupCredential = props.setup?.credential || {};

const csrForm = useForm({
    key_name: props.credential_onboarding.key_name || setupCredential.key_name || defaultKeyName,
    common_name: props.configuration.external_business_id || '',
    organization_name: '',
    country_name: 'AR',
});

const certificateForm = useForm({
    credential_id: props.credential_onboarding.credential_id || setupCredential.id || '',
    certificate: '',
    active: true,
});

const certificateFileName = ref('');

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

const diagnosticClass = (check) => {
    if (check.ok) return toneClass('emerald');
    if (check.skipped) return toneClass('amber');

    return toneClass('rose');
};

const connectionTone = computed(() => (
    props.connection.ok ? 'emerald' : (props.connection.status === 'uncertain' ? 'amber' : 'rose')
));

const setupTone = computed(() => (props.setup.ready ? 'emerald' : 'amber'));
const diagnosticsTone = computed(() => {
    if (!props.diagnostics.requested) return 'cyan';
    if (props.diagnostics.ok) return 'emerald';
    if (props.diagnostics.status === 'offline' || props.diagnostics.status === 'error') return 'rose';

    return 'amber';
});
const diagnosticsLabel = computed(() => {
    if (!props.diagnostics.requested) return 'No ejecutado';
    if (props.diagnostics.ok) return 'Sin errores';
    if (props.diagnostics.status === 'offline') return 'API no disponible';
    if (props.diagnostics.status === 'error') return 'Error';

    return 'Revisar';
});
const diagnosticRows = computed(() => (
    Array.isArray(props.diagnostics.checks) ? props.diagnostics.checks : []
));
const ivaSalesBook = computed(() => props.iva_sales_book || {});
const ivaMonth = ref(props.iva_sales_book?.period?.month || new Date().toISOString().slice(0, 7));
const ivaBookTone = computed(() => {
    if (!ivaSalesBook.value.requested) return 'cyan';
    if (ivaSalesBook.value.ok) return 'emerald';
    if (ivaSalesBook.value.status === 'offline' || ivaSalesBook.value.status === 'error') return 'rose';

    return 'amber';
});
const ivaBookLabel = computed(() => {
    if (!ivaSalesBook.value.requested) return 'Sin cargar';
    if (ivaSalesBook.value.ok) return 'Cargado';
    if (ivaSalesBook.value.status === 'offline') return 'API no disponible';
    if (ivaSalesBook.value.status === 'error') return 'Error';

    return 'Revisar';
});
const ivaRecords = computed(() => (
    Array.isArray(ivaSalesBook.value.records) ? ivaSalesBook.value.records : []
));
const ivaAliquots = computed(() => (
    Array.isArray(ivaSalesBook.value.totals?.iva_by_aliquot)
        ? ivaSalesBook.value.totals.iva_by_aliquot
        : []
));
const fiscalCuitLabel = computed(() => formatCuit(props.configuration.fiscal_cuit));

const voucherTypeName = (type) => {
    const labels = {
        1: 'Factura A',
        2: 'Nota debito A',
        3: 'Nota credito A',
        6: 'Factura B',
        7: 'Nota debito B',
        8: 'Nota credito B',
        11: 'Factura C',
        12: 'Nota debito C',
        13: 'Nota credito C',
    };

    return labels[Number(type)] || `Comprobante ${type || '-'}`;
};

const voucherLabel = (document) => {
    if (!document.point_of_sale || !document.number) {
        return '-';
    }

    const typeLabel = voucherTypeName(document.cbte_type);
    const pointOfSale = String(document.point_of_sale).padStart(5, '0');
    const number = String(document.number).padStart(8, '0');

    return `${typeLabel} ${pointOfSale}-${number}`;
};

const ivaRateLabel = (rate) => `${Number(rate || 0).toLocaleString('es-AR')}%`;

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
const credential = computed(() => props.setup?.credential || {});
const generatedCsr = computed(() => props.credential_onboarding?.csr || '');
const hasGeneratedCsr = computed(() => Boolean(generatedCsr.value || credential.value.csr_generated));
const hasLoadedCertificate = computed(() => Boolean(credential.value.certificate_loaded));
const generatedCredentialId = computed(() => props.credential_onboarding?.credential_id || credential.value.id || '');
const credentialKeyName = computed(() => props.credential_onboarding?.key_name || credential.value.key_name || '-');
const credentialStatus = computed(() => props.credential_onboarding?.credential_status || credential.value.status || '-');
const csrPlaceholder = computed(() => {
    if (generatedCsr.value) return generatedCsr.value;
    if (hasLoadedCertificate.value) return 'El certificado ya esta cargado en la API fiscal.';
    if (hasGeneratedCsr.value) return 'El CSR ya fue generado en la API fiscal. No se muestra nuevamente desde el estado.';

    return 'Todavia no se genero un CSR en esta sesion.';
});

const generateCsr = () => {
    if (hasGeneratedCsr.value || hasLoadedCertificate.value) return;

    csrForm.post(route('electronic-billing.credentials.csr'), {
        preserveScroll: true,
        preserveState: false,
    });
};

const uploadCertificate = () => {
    if (hasLoadedCertificate.value) return;

    certificateForm.post(route('electronic-billing.credentials.certificate.store'), {
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => certificateForm.reset('certificate'),
    });
};

const loadCertificateFile = async (event) => {
    const file = event.target.files?.[0];

    if (!file) return;

    certificateFileName.value = file.name;
    certificateForm.certificate = await file.text();
};

const copyCsr = async () => {
    if (!generatedCsr.value || !navigator.clipboard) return;

    await navigator.clipboard.writeText(generatedCsr.value);
};

const downloadCsr = () => {
    if (!generatedCsr.value) return;

    const blob = new Blob([generatedCsr.value], { type: 'application/pkcs10' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `${csrForm.key_name.replace(/\.key$/i, '') || 'credencial-fiscal'}.csr`;
    link.click();
    URL.revokeObjectURL(url);
};

const loadIvaSalesBook = () => {
    router.get(route('electronic-billing.index'), {
        iva_month: ivaMonth.value,
        load_iva_sales: 1,
    }, {
        preserveScroll: true,
        preserveState: false,
    });
};

const hideIvaSalesBook = () => {
    router.get(route('electronic-billing.index'), {
        iva_month: ivaMonth.value,
    }, {
        preserveScroll: true,
        preserveState: false,
    });
};

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

                    <div class="mt-5 rounded-xl border border-cyan-100/20 bg-slate-950/25 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h4 class="text-sm font-semibold text-slate-100">Diagnostico API</h4>
                                <p class="mt-1 text-sm text-slate-300/80">{{ diagnostics.message || 'Validacion profunda de credencial, WSAA y WSFEv1.' }}</p>
                            </div>
                            <span class="rounded-full border px-3 py-1 text-xs font-semibold" :class="toneClass(diagnosticsTone)">
                                {{ diagnosticsLabel }}
                            </span>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <Link
                                :href="route('electronic-billing.index', { run_diagnostics: 1 })"
                                class="rounded-lg border border-cyan-100/25 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70"
                                preserve-scroll
                            >
                                Ejecutar diagnostico
                            </Link>
                            <Link
                                v-if="diagnostics.requested"
                                :href="route('electronic-billing.index')"
                                class="rounded-lg border border-cyan-100/20 px-4 py-2 text-sm font-semibold text-slate-400 hover:bg-slate-800/60"
                                preserve-scroll
                            >
                                Ocultar
                            </Link>
                        </div>

                        <div v-if="diagnosticRows.length" class="mt-4 grid gap-2">
                            <article
                                v-for="check in diagnosticRows"
                                :key="check.key"
                                class="rounded-lg border px-3 py-2 text-sm"
                                :class="diagnosticClass(check)"
                            >
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <p class="font-semibold">{{ check.label }}</p>
                                    <span class="text-xs font-semibold">
                                        {{ check.ok ? 'OK' : (check.skipped ? 'Omitido' : 'Revisar') }}
                                    </span>
                                </div>
                                <p v-if="check.message" class="mt-1 text-xs opacity-90">{{ check.message }}</p>
                                <p v-if="check.error_code" class="mt-1 text-xs opacity-80">Codigo: {{ check.error_code }}</p>
                            </article>
                        </div>
                    </div>
                </article>
            </section>

            <section v-if="can_manage_credentials" class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-sm backdrop-blur">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-slate-100">Credencial fiscal API</h3>
                        <p class="mt-1 text-sm text-slate-300/80">CSR y certificado administrados por la API fiscal.</p>
                    </div>
                    <span v-if="generatedCredentialId" class="rounded-full border border-cyan-100/25 bg-slate-950/35 px-3 py-1 text-xs font-semibold text-slate-200">
                        Credencial #{{ generatedCredentialId }}
                    </span>
                </div>

                <p
                    v-if="hasLoadedCertificate"
                    class="mt-4 rounded-lg border border-emerald-200/35 bg-emerald-400/10 px-3 py-2 text-sm font-semibold text-emerald-100"
                >
                    La credencial fiscal ya tiene certificado cargado en la API.
                </p>
                <p
                    v-else-if="hasGeneratedCsr"
                    class="mt-4 rounded-lg border border-amber-200/35 bg-amber-400/10 px-3 py-2 text-sm font-semibold text-amber-100"
                >
                    El CSR ya fue generado. Carga el CRT devuelto por ARCA para activar la credencial.
                </p>

                <div class="mt-5 grid gap-5 xl:grid-cols-2">
                    <form class="rounded-xl border border-cyan-100/20 bg-slate-950/25 p-4" @submit.prevent="generateCsr">
                        <h4 class="text-sm font-semibold text-slate-100">Generar CSR</h4>

                        <div class="mt-4 grid gap-3">
                            <label class="grid gap-1 text-sm text-slate-300">
                                <span class="text-xs uppercase tracking-[0.18em] text-slate-400">Key name API</span>
                                <input
                                    v-model="csrForm.key_name"
                                    type="text"
                                    class="rounded-lg border border-cyan-100/20 bg-slate-950/50 px-3 py-2 text-sm text-slate-100 outline-none focus:border-cyan-200/60"
                                    autocomplete="off"
                                    :disabled="hasGeneratedCsr || hasLoadedCertificate"
                                >
                            </label>
                            <label class="grid gap-1 text-sm text-slate-300">
                                <span class="text-xs uppercase tracking-[0.18em] text-slate-400">Common name</span>
                                <input
                                    v-model="csrForm.common_name"
                                    type="text"
                                    class="rounded-lg border border-cyan-100/20 bg-slate-950/50 px-3 py-2 text-sm text-slate-100 outline-none focus:border-cyan-200/60"
                                    autocomplete="off"
                                    :disabled="hasGeneratedCsr || hasLoadedCertificate"
                                >
                            </label>
                            <label class="grid gap-1 text-sm text-slate-300">
                                <span class="text-xs uppercase tracking-[0.18em] text-slate-400">Razon social</span>
                                <input
                                    v-model="csrForm.organization_name"
                                    type="text"
                                    class="rounded-lg border border-cyan-100/20 bg-slate-950/50 px-3 py-2 text-sm text-slate-100 outline-none focus:border-cyan-200/60"
                                    autocomplete="off"
                                    :disabled="hasGeneratedCsr || hasLoadedCertificate"
                                >
                            </label>
                            <label class="grid gap-1 text-sm text-slate-300">
                                <span class="text-xs uppercase tracking-[0.18em] text-slate-400">Pais</span>
                                <input
                                    v-model="csrForm.country_name"
                                    type="text"
                                    maxlength="2"
                                    class="w-24 rounded-lg border border-cyan-100/20 bg-slate-950/50 px-3 py-2 text-sm uppercase text-slate-100 outline-none focus:border-cyan-200/60"
                                    autocomplete="off"
                                    :disabled="hasGeneratedCsr || hasLoadedCertificate"
                                >
                            </label>
                        </div>

                        <button
                            type="submit"
                            class="mt-4 inline-flex items-center justify-center rounded-lg border border-cyan-100/25 px-4 py-2 text-sm font-semibold text-slate-200 hover:bg-slate-800/70 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="csrForm.processing || hasGeneratedCsr || hasLoadedCertificate"
                        >
                            {{ csrForm.processing ? 'Generando...' : 'Generar CSR' }}
                        </button>
                    </form>

                    <div class="rounded-xl border border-cyan-100/20 bg-slate-950/25 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h4 class="text-sm font-semibold text-slate-100">CSR generado</h4>
                            <div v-if="generatedCsr" class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="rounded-lg border border-cyan-100/25 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-800/70"
                                    @click="copyCsr"
                                >
                                    Copiar
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg border border-cyan-100/25 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-800/70"
                                    @click="downloadCsr"
                                >
                                    Descargar
                                </button>
                            </div>
                        </div>

                        <textarea
                            :value="csrPlaceholder"
                            readonly
                            rows="12"
                            class="mt-4 w-full resize-y rounded-lg border border-cyan-100/20 bg-slate-950/50 px-3 py-2 font-mono text-xs text-slate-100 outline-none"
                        />

                        <dl v-if="hasGeneratedCsr || hasLoadedCertificate" class="mt-3 grid gap-2 text-xs text-slate-300 sm:grid-cols-2">
                            <div>
                                <dt class="uppercase tracking-[0.18em] text-slate-400">Key name</dt>
                                <dd class="mt-1 font-semibold text-slate-100">{{ credentialKeyName }}</dd>
                            </div>
                            <div>
                                <dt class="uppercase tracking-[0.18em] text-slate-400">Estado API</dt>
                                <dd class="mt-1 font-semibold text-slate-100">{{ credentialStatus }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <form class="mt-5 rounded-xl border border-cyan-100/20 bg-slate-950/25 p-4" @submit.prevent="uploadCertificate">
                    <h4 class="text-sm font-semibold text-slate-100">Cargar certificado CRT en API</h4>

                    <div class="mt-4 grid gap-3 xl:grid-cols-[12rem_minmax(0,1fr)]">
                        <label class="grid gap-1 text-sm text-slate-300">
                            <span class="text-xs uppercase tracking-[0.18em] text-slate-400">Credencial API</span>
                            <input
                                v-model="certificateForm.credential_id"
                                type="number"
                                min="1"
                                class="rounded-lg border border-cyan-100/20 bg-slate-950/50 px-3 py-2 text-sm text-slate-100 outline-none focus:border-cyan-200/60"
                                :disabled="hasLoadedCertificate"
                            >
                        </label>
                        <label class="grid gap-1 text-sm text-slate-300">
                            <span class="text-xs uppercase tracking-[0.18em] text-slate-400">Archivo CRT</span>
                            <input
                                type="file"
                                accept=".crt,.cer,.pem,text/plain,application/x-x509-ca-cert"
                                class="rounded-lg border border-cyan-100/20 bg-slate-950/50 px-3 py-2 text-sm text-slate-100 file:mr-3 file:rounded-md file:border-0 file:bg-cyan-400/20 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-cyan-100"
                                @change="loadCertificateFile"
                                :disabled="hasLoadedCertificate"
                            >
                            <span v-if="certificateFileName" class="text-xs text-slate-400">{{ certificateFileName }}</span>
                        </label>
                    </div>

                    <label class="mt-3 grid gap-1 text-sm text-slate-300">
                        <span class="text-xs uppercase tracking-[0.18em] text-slate-400">Contenido del CRT</span>
                        <textarea
                            v-model="certificateForm.certificate"
                            rows="8"
                            class="w-full resize-y rounded-lg border border-cyan-100/20 bg-slate-950/50 px-3 py-2 font-mono text-xs text-slate-100 outline-none focus:border-cyan-200/60"
                            autocomplete="off"
                            :disabled="hasLoadedCertificate"
                        />
                    </label>

                    <label class="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-slate-200">
                        <input
                            v-model="certificateForm.active"
                            type="checkbox"
                            class="rounded border-cyan-100/30 bg-slate-950/60 text-cyan-400 focus:ring-cyan-300"
                            :disabled="hasLoadedCertificate"
                        >
                        Activar credencial
                    </label>

                    <button
                        type="submit"
                        class="mt-4 inline-flex items-center justify-center rounded-lg border border-emerald-200/45 px-4 py-2 text-sm font-semibold text-emerald-100 hover:bg-emerald-400/10 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="certificateForm.processing || hasLoadedCertificate"
                    >
                        {{ certificateForm.processing ? 'Cargando...' : 'Cargar CRT en API' }}
                    </button>
                </form>
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
                    <div v-if="configuration.authorization_mode === 'caea'">
                        <dt class="text-xs uppercase tracking-[0.18em] text-slate-400">CAEA vigente</dt>
                        <dd class="mt-1 font-semibold text-slate-100">{{ configuration.caea?.code || '-' }}</dd>
                        <p v-if="configuration.caea?.period" class="mt-1 text-xs text-slate-400">
                            {{ configuration.caea.period }} / orden {{ configuration.caea.order || '-' }}
                        </p>
                    </div>
                    <div class="md:col-span-2">
                        <dt class="text-xs uppercase tracking-[0.18em] text-slate-400">Actividades configuradas</dt>
                        <dd class="mt-1 font-semibold text-slate-100">{{ compactList(configuration.activities || []) }}</dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-sm backdrop-blur">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h3 class="text-base font-semibold text-slate-100">Libro IVA Ventas</h3>
                        <p class="mt-1 text-sm text-slate-300/80">{{ ivaSalesBook.message || 'Consulta fiscal mensual desde la API.' }}</p>
                    </div>
                    <span class="rounded-full border px-3 py-1 text-xs font-semibold" :class="toneClass(ivaBookTone)">
                        {{ ivaBookLabel }}
                    </span>
                </div>

                <div class="mt-4 flex flex-wrap items-end gap-3">
                    <label class="grid gap-1 text-sm text-slate-300">
                        <span class="text-xs uppercase tracking-[0.18em] text-slate-400">Periodo</span>
                        <input
                            v-model="ivaMonth"
                            type="month"
                            class="rounded-lg border border-cyan-100/20 bg-slate-950/50 px-3 py-2 text-sm text-slate-100 outline-none focus:border-cyan-200/60"
                        >
                    </label>
                    <button
                        type="button"
                        class="rounded-lg border border-cyan-100/25 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70"
                        @click="loadIvaSalesBook"
                    >
                        Cargar libro
                    </button>
                    <button
                        v-if="ivaSalesBook.requested"
                        type="button"
                        class="rounded-lg border border-cyan-100/20 px-4 py-2 text-sm font-semibold text-slate-400 hover:bg-slate-800/60"
                        @click="hideIvaSalesBook"
                    >
                        Ocultar
                    </button>
                </div>

                <div v-if="ivaSalesBook.requested" class="mt-5">
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                        <div class="rounded-xl border border-cyan-100/20 bg-slate-950/25 p-4">
                            <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Total</p>
                            <p class="mt-2 text-lg font-semibold text-slate-100">{{ money(ivaSalesBook.totals?.imp_total) }}</p>
                        </div>
                        <div class="rounded-xl border border-cyan-100/20 bg-slate-950/25 p-4">
                            <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Neto</p>
                            <p class="mt-2 text-lg font-semibold text-slate-100">{{ money(ivaSalesBook.totals?.imp_neto) }}</p>
                        </div>
                        <div class="rounded-xl border border-cyan-100/20 bg-slate-950/25 p-4">
                            <p class="text-xs uppercase tracking-[0.18em] text-slate-400">IVA debito</p>
                            <p class="mt-2 text-lg font-semibold text-slate-100">{{ money(ivaSalesBook.totals?.imp_iva) }}</p>
                        </div>
                        <div class="rounded-xl border border-cyan-100/20 bg-slate-950/25 p-4">
                            <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Exento</p>
                            <p class="mt-2 text-lg font-semibold text-slate-100">{{ money(ivaSalesBook.totals?.imp_op_ex) }}</p>
                        </div>
                        <div class="rounded-xl border border-cyan-100/20 bg-slate-950/25 p-4">
                            <p class="text-xs uppercase tracking-[0.18em] text-slate-400">No gravado</p>
                            <p class="mt-2 text-lg font-semibold text-slate-100">{{ money(ivaSalesBook.totals?.imp_tot_conc) }}</p>
                        </div>
                    </div>

                    <div v-if="ivaAliquots.length" class="mt-4 flex flex-wrap gap-2">
                        <span
                            v-for="item in ivaAliquots"
                            :key="item.id"
                            class="rounded-lg border border-cyan-100/20 bg-slate-950/35 px-3 py-2 text-xs font-semibold text-slate-200"
                        >
                            IVA {{ ivaRateLabel(item.rate) }}: {{ money(item.importe) }} sobre {{ money(item.base_imp) }}
                        </span>
                    </div>

                    <div class="mt-4 overflow-x-auto rounded-xl border border-cyan-100/20 app-table-wrap">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-950/35">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium text-slate-300/80">Fecha</th>
                                    <th class="px-3 py-2 text-left font-medium text-slate-300/80">Comprobante</th>
                                    <th class="px-3 py-2 text-left font-medium text-slate-300/80">Cliente</th>
                                    <th class="px-3 py-2 text-left font-medium text-slate-300/80">Total</th>
                                    <th class="px-3 py-2 text-left font-medium text-slate-300/80">Neto</th>
                                    <th class="px-3 py-2 text-left font-medium text-slate-300/80">IVA</th>
                                    <th class="px-3 py-2 text-left font-medium text-slate-300/80">Alicuotas</th>
                                </tr>
                            </thead>
                            <tbody v-if="ivaRecords.length" class="divide-y divide-slate-100">
                                <tr v-for="record in ivaRecords" :key="record.id || `${record.cbte_type}-${record.point_of_sale}-${record.number}`">
                                    <td class="px-3 py-2 text-slate-300">{{ record.voucher_date || '-' }}</td>
                                    <td class="px-3 py-2 text-slate-200">{{ voucherLabel(record) }}</td>
                                    <td class="px-3 py-2 text-slate-300">
                                        <span>{{ record.counterparty_name || 'Consumidor final' }}</span>
                                        <p v-if="record.counterparty_cuit" class="mt-1 text-xs text-slate-400">{{ formatCuit(record.counterparty_cuit) }}</p>
                                    </td>
                                    <td class="px-3 py-2 text-slate-200">{{ money(record.amounts?.imp_total) }}</td>
                                    <td class="px-3 py-2 text-slate-200">{{ money(record.amounts?.imp_neto) }}</td>
                                    <td class="px-3 py-2 text-slate-200">{{ money(record.amounts?.imp_iva) }}</td>
                                    <td class="px-3 py-2 text-slate-300">
                                        <template v-if="record.iva_items?.length">
                                            <span v-for="item in record.iva_items" :key="item.id" class="block text-xs">
                                                {{ ivaRateLabel(item.rate) }}: {{ money(item.importe) }}
                                            </span>
                                        </template>
                                        <span v-else>-</span>
                                    </td>
                                </tr>
                            </tbody>
                            <tbody v-else>
                                <tr>
                                    <td colspan="7" class="px-3 py-6 text-center text-slate-400">Sin comprobantes autorizados para el periodo.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
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
