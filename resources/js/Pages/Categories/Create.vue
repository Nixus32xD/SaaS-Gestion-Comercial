<script setup>
import AppPanel from '@/Components/AppPanel.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    slug: '',
    description: '',
    is_active: true,
});

const categoryWarnings = computed(() => {
    const warnings = [];

    if (String(form.name || '').trim() === '') warnings.push('Falta el nombre de la categoria.');
    if (!form.is_active) warnings.push('La categoria nacera inactiva y no ayudara en filtros operativos.');

    return warnings;
});

const submit = () => {
    form.post(route('categories.store'));
};
</script>

<template>
    <Head title="Nueva categoria" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold text-slate-100">Nueva categoria</h2>
                    <p class="mt-1 text-sm text-slate-300/80">Crea categorias simples para ordenar productos.</p>
                </div>
                <Link :href="route('categories.index')" class="text-sm font-semibold text-slate-300 hover:text-slate-100">Volver</Link>
            </div>
        </template>

        <form class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_20rem]" @submit.prevent="submit">
            <AppPanel title="Datos de la categoria" subtitle="Usa nombres claros para que el equipo encuentre productos rapido en listados y filtros.">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="app-field">
                        <label class="app-field-label">Nombre</label>
                        <input v-model="form.name" type="text" class="w-full rounded-xl text-sm" placeholder="Ej: Bebidas" />
                    </div>
                    <div class="app-field">
                        <label class="app-field-label">Slug</label>
                        <input v-model="form.slug" type="text" class="w-full rounded-xl text-sm" placeholder="Opcional" />
                    </div>
                    <div class="app-field md:col-span-2">
                        <label class="app-field-label">Descripcion</label>
                        <textarea v-model="form.description" rows="3" class="w-full rounded-xl text-sm" placeholder="Descripcion opcional" />
                    </div>
                </div>

                <label class="mt-4 inline-flex items-center gap-2 text-sm text-slate-300">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-cyan-100/25">
                    Categoria activa
                </label>
            </AppPanel>

            <aside class="app-sticky-column">
                <AppPanel title="Resumen" :tone="categoryWarnings.length ? 'warning' : 'success'" subtitle="Chequeo corto antes de guardar.">
                    <div class="app-chip-row">
                        <StatusBadge :tone="form.is_active ? 'success' : 'neutral'" :label="form.is_active ? 'Activa' : 'Inactiva'" />
                    </div>

                    <div class="mt-4 space-y-3 text-sm text-slate-300">
                        <div class="flex items-center justify-between gap-3">
                            <span>Nombre</span>
                            <span class="text-right font-semibold text-slate-100">{{ form.name || 'Pendiente' }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span>Slug</span>
                            <span class="text-right font-semibold text-slate-100">{{ form.slug || 'Automatico' }}</span>
                        </div>
                    </div>

                    <div v-if="categoryWarnings.length" class="mt-4 rounded-xl border border-amber-300/25 bg-amber-400/10 p-3 text-sm text-amber-100">
                        <p v-for="warning in categoryWarnings" :key="warning">{{ warning }}</p>
                    </div>

                    <template #footer>
                        <div class="grid gap-3">
                            <button type="submit" class="w-full rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600" :disabled="form.processing">
                                Guardar categoria
                            </button>
                            <Link :href="route('categories.index')" class="inline-flex w-full items-center justify-center rounded-xl border border-cyan-100/25 px-4 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/70">
                                Volver
                            </Link>
                        </div>
                    </template>
                </AppPanel>
            </aside>
        </form>
    </AuthenticatedLayout>
</template>
