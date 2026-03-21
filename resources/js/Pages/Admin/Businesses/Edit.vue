<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    business: {
        type: Object,
        required: true,
    },
    sales_settings: {
        type: Object,
        required: true,
    },
});

const makeSector = () => ({
    id: null,
    name: '',
    description: '',
    is_active: true,
});

const makePaymentDestination = () => ({
    id: null,
    name: '',
    account_holder: '',
    reference: '',
    account_number: '',
    is_active: true,
});

const form = useForm({
    name: props.business.name,
    slug: props.business.slug || '',
    owner_name: props.business.owner_name || '',
    email: props.business.email || '',
    phone: props.business.phone || '',
    address: props.business.address || '',
    is_active: Boolean(props.business.is_active),
});

const salesSettingsForm = useForm({
    advanced_sale_settings_enabled: Boolean(props.sales_settings.advanced_sale_settings_enabled),
    global_product_catalog_enabled: Boolean(props.sales_settings.global_product_catalog_enabled),
    sale_sectors: (props.sales_settings.sale_sectors || []).map((sector) => ({
        id: sector.id,
        name: sector.name || '',
        description: sector.description || '',
        is_active: Boolean(sector.is_active),
    })),
    payment_destinations: (props.sales_settings.payment_destinations || []).map((destination) => ({
        id: destination.id,
        name: destination.name || '',
        account_holder: destination.account_holder || '',
        reference: destination.reference || '',
        account_number: destination.account_number || '',
        is_active: Boolean(destination.is_active),
    })),
});

const submit = () => {
    form.put(route('admin.businesses.update', props.business.id));
};

const submitSalesSettings = () => {
    salesSettingsForm.put(route('admin.businesses.sales-settings.update', props.business.id));
};

const addSector = () => {
    salesSettingsForm.sale_sectors.push(makeSector());
};

const addPaymentDestination = () => {
    salesSettingsForm.payment_destinations.push(makePaymentDestination());
};

const removeSector = (index) => {
    const sector = salesSettingsForm.sale_sectors[index];

    if (sector?.id) {
        salesSettingsForm.sale_sectors[index].is_active = false;
        return;
    }

    salesSettingsForm.sale_sectors.splice(index, 1);
};

const removePaymentDestination = (index) => {
    const destination = salesSettingsForm.payment_destinations[index];

    if (destination?.id) {
        salesSettingsForm.payment_destinations[index].is_active = false;
        return;
    }

    salesSettingsForm.payment_destinations.splice(index, 1);
};
</script>

<template>
    <Head title="Editar comercio" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold text-slate-100">Editar comercio</h2>
                    <p class="mt-1 text-sm text-slate-300/80">Actualiza datos generales y estado.</p>
                </div>
                <Link
                    :href="route('admin.businesses.index')"
                    class="rounded-lg border border-cyan-100/25 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70"
                >
                    Volver
                </Link>
            </div>
        </template>

        <div class="grid gap-6">
            <form class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-sm backdrop-blur" @submit.prevent="submit">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-100">Datos generales</h3>
                        <p class="mt-1 text-sm text-slate-300/80">Configuracion base del comercio.</p>
                    </div>
                </div>

                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-slate-300">Nombre</label>
                        <input v-model="form.name" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Nombre del comercio" />
                    </div>
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-slate-300">Slug</label>
                        <input v-model="form.slug" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Slug (opcional)" />
                    </div>
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-slate-300">Responsable</label>
                        <input v-model="form.owner_name" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Nombre del responsable" />
                    </div>
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-slate-300">Email</label>
                        <input v-model="form.email" type="email" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Email del comercio" />
                    </div>
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-slate-300">Telefono</label>
                        <input v-model="form.phone" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Telefono de contacto" />
                    </div>
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-slate-300">Direccion</label>
                        <input v-model="form.address" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Direccion del comercio" />
                    </div>
                </div>

                <label class="mt-4 inline-flex items-center gap-2 text-sm text-slate-300">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-cyan-100/25 bg-slate-950/35 text-indigo-500 focus:ring-indigo-500">
                    Comercio activo
                </label>

                <div class="mt-5 flex justify-end">
                    <button
                        type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        Guardar datos generales
                    </button>
                </div>
            </form>

            <form class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-sm backdrop-blur" @submit.prevent="submitSalesSettings">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-100">Funciones por comercio</h3>
                        <p class="mt-1 text-sm text-slate-300/80">Configuracion exclusiva para ventas avanzadas y acceso al catalogo global.</p>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="inline-flex items-center gap-2 rounded-xl border border-cyan-100/20 bg-slate-950/35 px-3 py-2 text-sm text-slate-200">
                            <input v-model="salesSettingsForm.advanced_sale_settings_enabled" type="checkbox" class="rounded border-cyan-100/25 bg-slate-950/35 text-indigo-500 focus:ring-indigo-500">
                            Habilitar ventas avanzadas
                        </label>
                        <label class="inline-flex items-center gap-2 rounded-xl border border-cyan-100/20 bg-slate-950/35 px-3 py-2 text-sm text-slate-200">
                            <input v-model="salesSettingsForm.global_product_catalog_enabled" type="checkbox" class="rounded border-cyan-100/25 bg-slate-950/35 text-indigo-500 focus:ring-indigo-500">
                            Habilitar catalogo global de productos
                        </label>
                    </div>
                </div>

                <p v-if="salesSettingsForm.errors.sale_sectors" class="mt-3 text-sm text-rose-300">{{ salesSettingsForm.errors.sale_sectors }}</p>
                <p v-if="salesSettingsForm.errors.payment_destinations" class="mt-2 text-sm text-rose-300">{{ salesSettingsForm.errors.payment_destinations }}</p>

                <div class="mt-5 grid gap-6 xl:grid-cols-2">
                    <section class="rounded-2xl border border-cyan-100/20 bg-slate-950/35 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h4 class="text-base font-semibold text-slate-100">Sectores / puntos de venta</h4>
                                <p class="mt-1 text-xs text-slate-400">Ejemplo: Almacen, Viviendas.</p>
                            </div>
                            <button type="button" class="rounded-lg border border-cyan-100/25 px-3 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-800/70" @click="addSector">
                                Agregar sector
                            </button>
                        </div>

                        <div class="mt-4 space-y-3">
                            <article v-for="(sector, index) in salesSettingsForm.sale_sectors" :key="`sector-${sector.id ?? `new-${index}`}`" class="rounded-xl border border-cyan-100/15 bg-slate-900/45 p-4">
                                <div class="grid gap-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="text-sm font-semibold text-slate-100">Sector {{ index + 1 }}</p>
                                        <div class="flex items-center gap-3">
                                            <label class="inline-flex items-center gap-2 text-xs text-slate-300">
                                                <input v-model="sector.is_active" type="checkbox" class="rounded border-cyan-100/25 bg-slate-950/35 text-indigo-500 focus:ring-indigo-500">
                                                Activo
                                            </label>
                                            <button type="button" class="text-xs font-semibold text-rose-200 hover:text-rose-100" @click="removeSector(index)">
                                                {{ sector.id ? 'Desactivar' : 'Quitar' }}
                                            </button>
                                        </div>
                                    </div>

                                    <div class="space-y-1">
                                        <label class="text-sm font-medium text-slate-300">Nombre</label>
                                        <input v-model="sector.name" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Nombre del sector" />
                                    </div>

                                    <div class="space-y-1">
                                        <label class="text-sm font-medium text-slate-300">Descripcion</label>
                                        <input v-model="sector.description" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Descripcion opcional" />
                                    </div>
                                </div>
                            </article>

                            <p v-if="!salesSettingsForm.sale_sectors.length" class="rounded-xl border border-dashed border-cyan-100/20 px-4 py-5 text-sm text-slate-400">
                                Todavia no hay sectores configurados.
                            </p>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-cyan-100/20 bg-slate-950/35 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h4 class="text-base font-semibold text-slate-100">Cuentas de cobro / destino</h4>
                                <p class="mt-1 text-xs text-slate-400">Mercado Pago, banco, alias o referencia equivalente.</p>
                            </div>
                            <button type="button" class="rounded-lg border border-cyan-100/25 px-3 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-800/70" @click="addPaymentDestination">
                                Agregar cuenta
                            </button>
                        </div>

                        <div class="mt-4 space-y-3">
                            <article v-for="(destination, index) in salesSettingsForm.payment_destinations" :key="`destination-${destination.id ?? `new-${index}`}`" class="rounded-xl border border-cyan-100/15 bg-slate-900/45 p-4">
                                <div class="grid gap-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="text-sm font-semibold text-slate-100">Cuenta {{ index + 1 }}</p>
                                        <div class="flex items-center gap-3">
                                            <label class="inline-flex items-center gap-2 text-xs text-slate-300">
                                                <input v-model="destination.is_active" type="checkbox" class="rounded border-cyan-100/25 bg-slate-950/35 text-indigo-500 focus:ring-indigo-500">
                                                Activa
                                            </label>
                                            <button type="button" class="text-xs font-semibold text-rose-200 hover:text-rose-100" @click="removePaymentDestination(index)">
                                                {{ destination.id ? 'Desactivar' : 'Quitar' }}
                                            </button>
                                        </div>
                                    </div>

                                    <div class="space-y-1">
                                        <label class="text-sm font-medium text-slate-300">Nombre identificatorio</label>
                                        <input v-model="destination.name" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Ej. Mercado Pago Almacen" />
                                    </div>

                                    <div class="grid gap-3 md:grid-cols-2">
                                        <div class="space-y-1">
                                            <label class="text-sm font-medium text-slate-300">Titular o descripcion</label>
                                            <input v-model="destination.account_holder" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Titular" />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-sm font-medium text-slate-300">Alias / referencia</label>
                                            <input v-model="destination.reference" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="Alias o referencia" />
                                        </div>
                                    </div>

                                    <div class="space-y-1">
                                        <label class="text-sm font-medium text-slate-300">Numero de cuenta o dato equivalente</label>
                                        <input v-model="destination.account_number" type="text" class="w-full rounded-xl border-cyan-100/25 bg-slate-950/35 text-sm text-slate-100 placeholder:text-slate-400" placeholder="CBU, CVU, cuenta, link, etc." />
                                    </div>
                                </div>
                            </article>

                            <p v-if="!salesSettingsForm.payment_destinations.length" class="rounded-xl border border-dashed border-cyan-100/20 px-4 py-5 text-sm text-slate-400">
                                Todavia no hay cuentas configuradas.
                            </p>
                        </div>
                    </section>
                </div>

                <div class="mt-5 flex justify-end">
                    <button
                        type="submit"
                        class="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-500 disabled:opacity-50"
                        :disabled="salesSettingsForm.processing"
                    >
                        Guardar funciones por comercio
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
