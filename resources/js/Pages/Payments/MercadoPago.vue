<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    settings: { type: Object, required: true },
    branch_point_settings: { type: Object, required: true },
    branch_name: { type: String, required: true },
    webhook_url: { type: String, required: true },
});

const page = usePage();
const hasMultipleBranches = computed(() => (
    Array.isArray(page.props.branches)
        ? page.props.branches.length > 1
        : Boolean(page.props.business?.has_multiple_branches)
));

const branchPointForm = useForm({
    is_enabled: Boolean(props.branch_point_settings.is_enabled),
    point_terminal_id: props.branch_point_settings.point_terminal_id || '',
    point_store_id: props.branch_point_settings.point_store_id || '',
    point_pos_id: props.branch_point_settings.point_pos_id || '',
    point_external_store_id: props.branch_point_settings.point_external_store_id || '',
    point_external_pos_id: props.branch_point_settings.point_external_pos_id || '',
    point_expiration_time: props.branch_point_settings.point_expiration_time || '',
    point_print_on_terminal: props.branch_point_settings.point_print_on_terminal || '',
});

const form = useForm({
    is_enabled: Boolean(props.settings.is_enabled),
    environment: props.settings.environment || 'testing',
    public_key: '',
    access_token: '',
    webhook_secret: '',
    point_terminal_id: props.settings.point_terminal_id || '',
    point_store_id: props.settings.point_store_id || '',
    point_pos_id: props.settings.point_pos_id || '',
    point_external_store_id: props.settings.point_external_store_id || '',
    point_external_pos_id: props.settings.point_external_pos_id || '',
    point_expiration_time: props.settings.point_expiration_time || 'PT15M',
    point_print_on_terminal: props.settings.point_print_on_terminal || 'no_ticket',
});

const statusItems = computed(() => [
    {
        label: 'Access Token',
        configured: Boolean(props.settings.access_token_configured),
    },
    {
        label: 'Terminal',
        configured: Boolean(form.point_terminal_id),
    },
    {
        label: 'Webhook',
        configured: Boolean(props.settings.webhook_secret_configured),
    },
]);

const copyWebhookUrl = async () => {
    if (!navigator.clipboard) return;

    await navigator.clipboard.writeText(props.webhook_url);
};

const submit = () => {
    form.put(route('mercadopago-settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.public_key = '';
            form.access_token = '';
            form.webhook_secret = '';
        },
    });
};

const submitBranchPoint = () => {
    branchPointForm.put(route('mercadopago-settings.branch-point.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Mercado Pago" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold text-slate-100">Mercado Pago</h2>
                    <p class="mt-1 text-sm text-slate-300/80">Credenciales Point del comercio.</p>
                </div>
                <Link :href="route('sales.create')" class="rounded-lg border border-cyan-100/25 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70">
                    Nueva venta
                </Link>
            </div>
        </template>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <form class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-sm backdrop-blur" @submit.prevent="submit">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-100">Configuracion Point</h3>
                        <p class="mt-1 text-sm text-slate-300/80">Se usa para enviar ventas a la terminal desde el POS.</p>
                    </div>
                    <label class="inline-flex items-center gap-2 rounded-xl border border-cyan-100/20 bg-slate-950/35 px-3 py-2 text-sm text-slate-200">
                        <input v-model="form.is_enabled" type="checkbox" class="rounded border-cyan-100/25 bg-slate-950/35 text-indigo-500 focus:ring-indigo-500">
                        Habilitar Point
                    </label>
                </div>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-slate-300">Ambiente</label>
                        <select v-model="form.environment" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100">
                            <option value="testing">Prueba</option>
                            <option value="production">Produccion</option>
                        </select>
                        <p v-if="form.errors.environment" class="text-xs text-rose-300">{{ form.errors.environment }}</p>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium text-slate-300">Terminal ID fallback</label>
                        <input v-model="form.point_terminal_id" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="NEWLAND_N950__..." />
                        <p v-if="form.errors.point_terminal_id" class="text-xs text-rose-300">{{ form.errors.point_terminal_id }}</p>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium text-slate-300">Public Key</label>
                        <input v-model="form.public_key" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" :placeholder="settings.public_key_configured ? 'Ya configurada' : 'APP_USR-...'" autocomplete="off" />
                        <p v-if="form.errors.public_key" class="text-xs text-rose-300">{{ form.errors.public_key }}</p>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium text-slate-300">Access Token</label>
                        <input v-model="form.access_token" type="password" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" :placeholder="settings.access_token_configured ? 'Ya configurado' : 'APP_USR-...'" autocomplete="new-password" />
                        <p v-if="form.errors.access_token" class="text-xs text-rose-300">{{ form.errors.access_token }}</p>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium text-slate-300">Clave secreta webhook</label>
                        <input v-model="form.webhook_secret" type="password" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" :placeholder="settings.webhook_secret_configured ? 'Ya configurada' : 'Clave de Mercado Pago'" autocomplete="new-password" />
                        <p v-if="form.errors.webhook_secret" class="text-xs text-rose-300">{{ form.errors.webhook_secret }}</p>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium text-slate-300">Expiracion de orden</label>
                        <input v-model="form.point_expiration_time" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="PT15M" />
                        <p v-if="form.errors.point_expiration_time" class="text-xs text-rose-300">{{ form.errors.point_expiration_time }}</p>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium text-slate-300">Impresion en terminal</label>
                        <select v-model="form.point_print_on_terminal" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100">
                            <option value="no_ticket">Sin ticket</option>
                            <option value="seller_ticket">Ticket vendedor</option>
                        </select>
                        <p v-if="form.errors.point_print_on_terminal" class="text-xs text-rose-300">{{ form.errors.point_print_on_terminal }}</p>
                    </div>
                </div>

                <section class="mt-5 rounded-2xl border border-cyan-100/20 bg-slate-950/35 p-4">
                    <h4 class="text-base font-semibold text-slate-100">{{ hasMultipleBranches ? 'Sucursal y caja' : 'Punto de venta y caja' }}</h4>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">Store ID</label>
                            <input v-model="form.point_store_id" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="86244114" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">POS ID</label>
                            <input v-model="form.point_pos_id" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="136820601" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">External Store ID</label>
                            <input v-model="form.point_external_store_id" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="SUC001" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">External POS ID</label>
                            <input v-model="form.point_external_pos_id" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="CAJA001" />
                        </div>
                    </div>
                </section>

                <section class="mt-5 rounded-2xl border border-indigo-200/25 bg-indigo-400/10 p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h4 class="text-base font-semibold text-slate-100">Terminal {{ hasMultipleBranches ? `de ${branch_name}` : 'Point' }}</h4>
                            <p class="mt-1 text-sm text-slate-300/80">
                                {{ hasMultipleBranches
                                    ? (branch_point_settings.configured ? 'Esta configuración reemplaza la terminal fallback para esta sucursal.' : 'Sin configuración propia: se usa la terminal fallback del comercio.')
                                    : 'Configura la terminal Point que se usará en el comercio.' }}
                            </p>
                        </div>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-200">
                            <input v-model="branchPointForm.is_enabled" type="checkbox" class="rounded border-cyan-100/25 bg-slate-950/35 text-indigo-500 focus:ring-indigo-500">
                            {{ hasMultipleBranches ? 'Habilitar Point en esta sucursal' : 'Habilitar Mercado Pago Point' }}
                        </label>
                    </div>

                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">Terminal ID</label>
                            <input v-model="branchPointForm.point_terminal_id" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100" placeholder="NEWLAND_N950__...">
                            <p v-if="branchPointForm.errors.point_terminal_id" class="text-xs text-rose-300">{{ branchPointForm.errors.point_terminal_id }}</p>
                        </div>
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">Expiración de orden</label>
                            <input v-model="branchPointForm.point_expiration_time" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100" placeholder="Usa la configuración fallback">
                        </div>
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">Store ID</label>
                            <input v-model="branchPointForm.point_store_id" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100">
                        </div>
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-slate-300">POS ID</label>
                            <input v-model="branchPointForm.point_pos_id" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100">
                        </div>
                    </div>

                    <div class="mt-4 flex justify-end">
                        <button type="button" class="rounded-lg border border-indigo-200/35 px-4 py-2 text-sm font-semibold text-indigo-100 hover:bg-indigo-400/15 disabled:opacity-50" :disabled="branchPointForm.processing" @click="submitBranchPoint">
                            {{ hasMultipleBranches ? 'Guardar terminal de sucursal' : 'Guardar terminal Point' }}
                        </button>
                    </div>
                </section>

                <div class="mt-5 flex justify-end">
                    <button type="submit" class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-500 disabled:opacity-50" :disabled="form.processing">
                        Guardar Mercado Pago
                    </button>
                </div>
            </form>

            <aside class="grid gap-6">
                <section class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-sm backdrop-blur">
                    <h3 class="text-base font-semibold text-slate-100">Estado</h3>
                    <div class="mt-4 grid gap-3">
                        <div v-for="item in statusItems" :key="item.label" class="flex items-center justify-between gap-3 rounded-xl border border-cyan-100/15 bg-slate-950/35 px-4 py-3 text-sm">
                            <span class="text-slate-300">{{ item.label }}</span>
                            <span class="font-semibold" :class="item.configured ? 'text-emerald-100' : 'text-amber-100'">
                                {{ item.configured ? 'Configurado' : 'Pendiente' }}
                            </span>
                        </div>
                    </div>
                </section>

                <section class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-sm backdrop-blur">
                    <h3 class="text-base font-semibold text-slate-100">Webhook</h3>
                    <div class="mt-3 rounded-xl border border-cyan-100/15 bg-slate-950/35 px-4 py-3">
                        <p class="break-all font-mono text-xs text-slate-200">{{ webhook_url }}</p>
                    </div>
                    <button type="button" class="mt-3 w-full rounded-lg border border-cyan-100/25 px-4 py-2 text-sm font-semibold text-cyan-100 hover:bg-slate-800/70" @click="copyWebhookUrl">
                        Copiar URL
                    </button>
                </section>
            </aside>
        </div>
    </AuthenticatedLayout>
</template>
