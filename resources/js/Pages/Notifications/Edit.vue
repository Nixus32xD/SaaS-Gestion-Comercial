<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    settings: { type: Object, required: true },
    recipient_preview: { type: Array, default: () => [] },
    alerts_preview: { type: Object, default: () => ({ low_stock: { summary: {}, items: [] }, expiration: { summary: {}, items: [] } }) },
    maintenance_preview: { type: Object, default: () => ({}) },
    recent_dispatches: { type: Array, default: () => [] },
});

const form = useForm({
    notifications_enabled: Boolean(props.settings.notifications_enabled),
    send_to_business_email: Boolean(props.settings.send_to_business_email),
    send_to_admin_users: Boolean(props.settings.send_to_admin_users),
    low_stock_enabled: Boolean(props.settings.low_stock_enabled),
    expiration_enabled: Boolean(props.settings.expiration_enabled),
    maintenance_due_enabled: Boolean(props.settings.maintenance_due_enabled),
    minimum_hours_between_alerts: Number(props.settings.minimum_hours_between_alerts || 12),
    notification_window_start_hour: Number(props.settings.notification_window_start_hour ?? 9),
    notification_window_end_hour: Number(props.settings.notification_window_end_hour ?? 18),
    extra_recipients_text: props.settings.extra_recipients_text || '',
});

const submit = () => {
    form.put(route('notifications.update'));
};

const formattedWindow = computed(() => {
    const startHour = String(Math.max(0, Math.min(23, Number(form.notification_window_start_hour) || 0))).padStart(2, '0');
    const endHour = String(Math.max(0, Math.min(23, Number(form.notification_window_end_hour) || 0))).padStart(2, '0');
    const crossesMidnight = Number(form.notification_window_start_hour) > Number(form.notification_window_end_hour);

    return crossesMidnight
        ? `${startHour}:00 a ${endHour}:00 (cruza medianoche)`
        : `${startHour}:00 a ${endHour}:00`;
});

const hasAnyAutomaticMailEnabled = computed(() => (
    Boolean(form.notifications_enabled) || Boolean(form.maintenance_due_enabled)
));

const dispatchBadgeClass = (status) => {
    if (status === 'sent') {
        return 'bg-emerald-100 text-emerald-700';
    }

    if (status === 'queued') {
        return 'bg-sky-100 text-sky-700';
    }

    if (status === 'partial') {
        return 'bg-amber-100 text-amber-700';
    }

    return 'bg-rose-100 text-rose-700';
};

const dispatchStatusLabel = (status) => {
    if (status === 'sent') {
        return 'enviado';
    }

    if (status === 'queued') {
        return 'en cola';
    }

    if (status === 'partial') {
        return 'parcial';
    }

    return 'fallido';
};

const dispatchTypeLabel = (type) => {
    if (type === 'maintenance_due_reminder') {
        return 'mantenimiento';
    }

    return 'operativa';
};
</script>

<template>
    <Head title="Notificaciones" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-2xl font-bold text-slate-100">Notificaciones</h2>
                <p class="mt-1 text-sm text-slate-300">Define a quien avisar y con que frecuencia enviar alertas operativas y recordatorios de abono.</p>
            </div>
        </template>

        <div class="grid gap-6">
            <section class="grid gap-4 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]">
                <form class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-4 shadow-[0_20px_45px_rgba(8,47,73,0.36)] backdrop-blur sm:p-5" @submit.prevent="submit">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-slate-100">Configuracion de envio</h3>
                            <p class="mt-1 text-sm text-slate-300">Estas opciones se aplican al comercio actual.</p>
                        </div>
                        <button
                            type="submit"
                            class="w-full rounded-xl bg-cyan-300 px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-cyan-200 sm:w-auto"
                            :disabled="form.processing"
                        >
                            Guardar cambios
                        </button>
                    </div>

                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <label class="flex items-start gap-3 rounded-xl border border-cyan-100/15 bg-slate-950/35 px-4 py-3 text-sm text-slate-100 md:col-span-2">
                            <input v-model="form.notifications_enabled" type="checkbox" class="mt-0.5 rounded border-cyan-100/25 bg-slate-950/35 text-cyan-300 focus:ring-cyan-300" />
                            <span>
                                <span class="block font-medium text-slate-100">Notificaciones operativas activas</span>
                                <span class="mt-1 block text-xs text-slate-400">Controla solo las alertas automaticas de stock y vencimientos. El recordatorio de mantenimiento se maneja aparte.</span>
                            </span>
                        </label>

                        <label class="flex items-start gap-3 rounded-xl border border-cyan-100/15 bg-slate-950/35 px-4 py-3 text-sm text-slate-100">
                            <input v-model="form.send_to_business_email" type="checkbox" class="rounded border-cyan-100/25 bg-slate-950/35 text-cyan-300 focus:ring-cyan-300" />
                            <span>
                                <span class="block font-medium text-slate-100">Usar email principal del comercio</span>
                                <span class="mt-1 block text-xs text-slate-400">Ideal para que el duenio o casilla principal reciba el resumen.</span>
                            </span>
                        </label>

                        <label class="flex items-start gap-3 rounded-xl border border-cyan-100/15 bg-slate-950/35 px-4 py-3 text-sm text-slate-100">
                            <input v-model="form.send_to_admin_users" type="checkbox" class="rounded border-cyan-100/25 bg-slate-950/35 text-cyan-300 focus:ring-cyan-300" />
                            <span>
                                <span class="block font-medium text-slate-100">Incluir admins activos del comercio</span>
                                <span class="mt-1 block text-xs text-slate-400">Suma a todos los usuarios internos con rol admin y estado activo.</span>
                            </span>
                        </label>

                        <label class="flex items-start gap-3 rounded-xl border border-cyan-100/15 bg-slate-950/35 px-4 py-3 text-sm text-slate-100">
                            <input v-model="form.low_stock_enabled" type="checkbox" class="rounded border-cyan-100/25 bg-slate-950/35 text-cyan-300 focus:ring-cyan-300" />
                            <span>
                                <span class="block font-medium text-slate-100">Alertas de bajo stock y agotados</span>
                                <span class="mt-1 block text-xs text-slate-400">Avisa cuando un producto cae al minimo o se queda sin stock.</span>
                            </span>
                        </label>

                        <label class="flex items-start gap-3 rounded-xl border border-cyan-100/15 bg-slate-950/35 px-4 py-3 text-sm text-slate-100">
                            <input v-model="form.expiration_enabled" type="checkbox" class="rounded border-cyan-100/25 bg-slate-950/35 text-cyan-300 focus:ring-cyan-300" />
                            <span>
                                <span class="block font-medium text-slate-100">Alertas de vencimientos</span>
                                <span class="mt-1 block text-xs text-slate-400">Incluye lotes vencidos y proximos a vencer segun el umbral del producto.</span>
                            </span>
                        </label>

                        <label class="flex items-start gap-3 rounded-xl border border-cyan-100/15 bg-slate-950/35 px-4 py-3 text-sm text-slate-100 md:col-span-2">
                            <input v-model="form.maintenance_due_enabled" type="checkbox" class="mt-0.5 rounded border-cyan-100/25 bg-slate-950/35 text-cyan-300 focus:ring-cyan-300" />
                            <span>
                                <span class="block font-medium text-slate-100">Recordatorio de mantenimiento por vencer</span>
                                <span class="mt-1 block text-xs text-slate-400">Envia un correo automatico cuando faltan 7 dias para el vencimiento del abono mensual.</span>
                            </span>
                        </label>
                    </div>

                    <div class="mt-5 grid gap-4 xl:grid-cols-[220px_minmax(0,1fr)]">
                        <div class="rounded-2xl border border-cyan-100/15 bg-slate-950/35 p-4">
                            <label class="text-sm font-medium text-slate-200">Horas minimas entre reenvios</label>
                            <input
                                v-model.number="form.minimum_hours_between_alerts"
                                type="number"
                                min="1"
                                max="168"
                                class="mt-2 w-full rounded-xl border-cyan-100/25 bg-white text-sm text-slate-900"
                            />
                            <p class="mt-2 text-xs text-slate-400">Si la alerta no cambia, no se reenviara antes de este plazo.</p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="rounded-2xl border border-cyan-100/15 bg-slate-950/35 p-4">
                                <label class="text-sm font-medium text-slate-200">Hora de apertura</label>
                                <input
                                    v-model.number="form.notification_window_start_hour"
                                    type="number"
                                    min="0"
                                    max="23"
                                    class="mt-2 w-full rounded-xl border-cyan-100/25 bg-white text-sm text-slate-900"
                                />
                                <p class="mt-2 text-xs text-slate-400">Hora inicial permitida para enviar alertas.</p>
                            </div>

                            <div class="rounded-2xl border border-cyan-100/15 bg-slate-950/35 p-4">
                                <label class="text-sm font-medium text-slate-200">Hora de cierre</label>
                                <input
                                    v-model.number="form.notification_window_end_hour"
                                    type="number"
                                    min="0"
                                    max="23"
                                    class="mt-2 w-full rounded-xl border-cyan-100/25 bg-white text-sm text-slate-900"
                                />
                                <p class="mt-2 text-xs text-slate-400">Hora final permitida. Si es menor que apertura, la ventana cruza medianoche.</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 rounded-2xl border border-cyan-100/15 bg-slate-950/35 p-4">
                        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                            <label class="text-sm font-medium text-slate-200">Emails adicionales</label>
                            <p class="text-xs text-cyan-100/70">Ventana activa: {{ formattedWindow }}</p>
                        </div>
                        <textarea
                            v-model="form.extra_recipients_text"
                            rows="6"
                            class="mt-2 w-full rounded-xl border-cyan-100/25 bg-white text-sm text-slate-900"
                            placeholder="compras@cliente.com&#10;dueno@cliente.com"
                        />
                        <p class="mt-2 text-xs text-slate-400">Uno por linea. Tambien puedes separar con coma o punto y coma.</p>
                    </div>
                </form>

                <aside class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-4 shadow-[0_20px_45px_rgba(8,47,73,0.36)] backdrop-blur sm:p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-slate-100">Destinatarios resueltos</h3>
                            <p class="mt-1 text-sm text-slate-300">Vista previa de quienes recibirian el proximo envio operativo o el recordatorio de mantenimiento.</p>
                        </div>
                        <span class="inline-flex w-fit rounded-full border px-3 py-1 text-xs font-semibold"
                            :class="hasAnyAutomaticMailEnabled
                                ? 'border-cyan-100/20 bg-cyan-400/10 text-cyan-100'
                                : 'border-slate-100/15 bg-slate-800/60 text-slate-300'"
                        >
                            {{ hasAnyAutomaticMailEnabled ? `${recipient_preview.length} destinatarios` : 'Pausadas' }}
                        </span>
                    </div>
                    <p class="mt-2 text-xs text-cyan-100/70">Ventana activa: {{ formattedWindow }}</p>

                    <p v-if="!hasAnyAutomaticMailEnabled" class="mt-4 rounded-xl border border-slate-100/10 bg-slate-950/35 px-3 py-3 text-sm text-slate-300">
                        No hay envios automaticos activos para este comercio.
                    </p>

                    <ul v-else-if="recipient_preview.length" class="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-1">
                        <li v-for="recipient in recipient_preview" :key="recipient.email" class="rounded-xl border border-cyan-100/15 bg-slate-950/35 px-3 py-3 text-sm">
                            <div class="flex items-start justify-between gap-2">
                                <p class="min-w-0 break-all font-medium text-slate-100">{{ recipient.email }}</p>
                                <span class="rounded-full border border-cyan-100/15 bg-cyan-400/10 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-cyan-100">
                                    {{ recipient.source }}
                                </span>
                            </div>
                        </li>
                    </ul>
                    <p v-else class="mt-4 rounded-xl border border-rose-300/30 bg-rose-400/10 px-3 py-3 text-sm text-rose-100">
                        No hay destinatarios configurados. La tarea automatica se omitira hasta que agregues al menos uno.
                    </p>
                </aside>
            </section>

            <section class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-4 shadow-[0_20px_45px_rgba(8,47,73,0.36)] backdrop-blur sm:p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-slate-100">Vista previa de mantenimiento</h3>
                        <p class="mt-1 text-sm text-slate-300">Referencia del estado actual del abono y del proximo recordatorio por mail.</p>
                    </div>
                    <span class="inline-flex w-fit rounded-full border px-3 py-1 text-xs font-semibold"
                        :class="maintenance_preview.tone === 'emerald'
                            ? 'border-emerald-200/30 bg-emerald-400/10 text-emerald-100'
                            : maintenance_preview.tone === 'amber'
                                ? 'border-amber-200/30 bg-amber-400/10 text-amber-100'
                                : maintenance_preview.tone === 'rose'
                                    ? 'border-rose-200/30 bg-rose-400/10 text-rose-100'
                                    : 'border-slate-100/15 bg-slate-800/60 text-slate-300'"
                    >
                        {{ maintenance_preview.status_label || 'Sin mantenimiento' }}
                    </span>
                </div>

                <div class="mt-4 grid gap-3 md:grid-cols-3">
                    <div class="rounded-xl border border-cyan-100/15 bg-slate-950/35 p-4">
                        <p class="text-xs uppercase tracking-wide text-slate-400">Plan</p>
                        <p class="mt-2 text-base font-semibold text-slate-100">{{ maintenance_preview.plan_title || '-' }}</p>
                    </div>
                    <div class="rounded-xl border border-cyan-100/15 bg-slate-950/35 p-4">
                        <p class="text-xs uppercase tracking-wide text-slate-400">Vencimiento</p>
                        <p class="mt-2 text-base font-semibold text-slate-100">{{ maintenance_preview.ends_at_label || '-' }}</p>
                    </div>
                    <div class="rounded-xl border border-cyan-100/15 bg-slate-950/35 p-4">
                        <p class="text-xs uppercase tracking-wide text-slate-400">Importe</p>
                        <p class="mt-2 text-base font-semibold text-slate-100">{{ maintenance_preview.amount_label || '-' }}</p>
                    </div>
                </div>

                <p class="mt-4 text-sm text-slate-300">{{ maintenance_preview.status_message || 'Todavia no hay un mantenimiento mensual cargado.' }}</p>
                <p class="mt-2 text-xs text-cyan-100/70">El aviso por correo sale 7 dias antes del vencimiento y usa los mismos destinatarios resueltos de esta pantalla, aunque las alertas operativas esten pausadas.</p>
            </section>

            <section class="grid gap-4 xl:grid-cols-2">
                <article class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-4 shadow-[0_20px_45px_rgba(8,47,73,0.36)] backdrop-blur sm:p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-slate-100">Vista previa de stock</h3>
                            <p class="mt-1 text-sm text-slate-300">Detectados {{ alerts_preview.low_stock?.summary?.total || 0 }} productos con alerta.</p>
                        </div>
                        <span class="inline-flex w-fit rounded-full border border-rose-300/30 bg-rose-400/12 px-3 py-1 text-xs font-semibold text-rose-100">
                            Agotados {{ alerts_preview.low_stock?.summary?.out_of_stock || 0 }}
                        </span>
                    </div>

                    <ul v-if="alerts_preview.low_stock?.items?.length" class="mt-4 space-y-2 text-sm">
                        <li v-for="item in alerts_preview.low_stock.items" :key="item.product_id" class="rounded-xl border border-rose-300/30 bg-rose-400/10 px-3 py-3 text-rose-100">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <p class="font-medium">{{ item.product_name }}</p>
                                <span class="inline-flex w-fit rounded-full border border-rose-200/20 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide">
                                    {{ item.status === 'out_of_stock' ? 'Agotado' : 'Bajo stock' }}
                                </span>
                            </div>
                            <p class="mt-2 text-sm text-rose-50/90">Actual {{ item.stock }} / minimo {{ item.min_stock }}</p>
                        </li>
                    </ul>
                    <p v-else class="mt-4 text-sm text-slate-300">No hay alertas activas de stock.</p>
                </article>

                <article class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-4 shadow-[0_20px_45px_rgba(8,47,73,0.36)] backdrop-blur sm:p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-slate-100">Vista previa de vencimientos</h3>
                            <p class="mt-1 text-sm text-slate-300">Vencidos {{ alerts_preview.expiration?.summary?.expired || 0 }} | En 7 dias {{ alerts_preview.expiration?.summary?.within_7_days || 0 }}</p>
                        </div>
                    </div>

                    <ul v-if="alerts_preview.expiration?.items?.length" class="mt-4 space-y-2 text-sm">
                        <li v-for="item in alerts_preview.expiration.items" :key="item.batch_id" class="rounded-xl border border-amber-300/30 bg-amber-400/10 px-3 py-3 text-amber-100">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <p class="font-medium">{{ item.product_name }}</p>
                                <span class="inline-flex w-fit rounded-full border border-amber-200/20 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide">
                                    {{ item.status === 'expired' ? 'Vencido' : 'Proximo' }}
                                </span>
                            </div>
                            <p class="mt-2 text-sm text-amber-50/90">
                                Lote {{ item.batch_code || '-' }} - {{ item.status === 'expired' ? 'vencido' : `vence en ${item.days_remaining} dias` }}
                            </p>
                        </li>
                    </ul>
                    <p v-else class="mt-4 text-sm text-slate-300">No hay lotes para alertar.</p>
                </article>
            </section>

            <section class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-4 shadow-[0_20px_45px_rgba(8,47,73,0.36)] backdrop-blur sm:p-5">
                <h3 class="text-base font-semibold text-slate-100">Ultimos envios</h3>
                <div class="mt-4 hidden overflow-x-auto rounded-xl border border-cyan-100/20 md:block">
                    <table class="min-w-full divide-y divide-cyan-100/15 text-sm">
                        <thead class="bg-slate-950/45">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-slate-300">Fecha</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300">Tipo</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300">Estado</th>
                                <th class="px-3 py-2 text-left font-medium text-slate-300">Asunto</th>
                                <th class="px-3 py-2 text-right font-medium text-slate-300">OK</th>
                                <th class="px-3 py-2 text-right font-medium text-slate-300">Fallidos</th>
                            </tr>
                        </thead>
                        <tbody v-if="recent_dispatches.length" class="divide-y divide-cyan-100/10 bg-slate-900/25">
                            <tr v-for="dispatch in recent_dispatches" :key="dispatch.id">
                                <td class="px-3 py-2 text-slate-200">{{ dispatch.attempted_at }}</td>
                                <td class="px-3 py-2 text-slate-200">{{ dispatchTypeLabel(dispatch.notification_type) }}</td>
                                <td class="px-3 py-2">
                                    <span
                                        class="rounded-full px-2 py-1 text-xs font-semibold"
                                        :class="dispatchBadgeClass(dispatch.status)"
                                    >
                                        {{ dispatchStatusLabel(dispatch.status) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-slate-100">{{ dispatch.subject || '-' }}</td>
                                <td class="px-3 py-2 text-right text-slate-200">{{ dispatch.successful_count }}</td>
                                <td class="px-3 py-2 text-right text-slate-200">{{ dispatch.failed_count }}</td>
                            </tr>
                        </tbody>
                        <tbody v-else>
                            <tr>
                                <td colspan="6" class="px-3 py-6 text-center text-slate-300">Todavia no hay envios registrados.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 grid gap-3 md:hidden">
                    <article v-for="dispatch in recent_dispatches" :key="dispatch.id" class="rounded-xl border border-cyan-100/15 bg-slate-950/35 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs uppercase tracking-wide text-slate-400">{{ dispatch.attempted_at }}</p>
                                <p class="mt-1 text-xs uppercase tracking-wide text-cyan-100/70">{{ dispatchTypeLabel(dispatch.notification_type) }}</p>
                                <p class="mt-1 font-medium text-slate-100">{{ dispatch.subject || '-' }}</p>
                            </div>
                            <span
                                class="rounded-full px-2 py-1 text-[11px] font-semibold"
                                :class="dispatchBadgeClass(dispatch.status)"
                            >
                                {{ dispatchStatusLabel(dispatch.status) }}
                            </span>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-2 text-sm text-slate-300">
                            <div class="rounded-lg border border-cyan-100/10 bg-slate-900/35 px-3 py-2">
                                <p class="text-[11px] uppercase tracking-wide text-slate-500">OK</p>
                                <p class="mt-1 text-slate-100">{{ dispatch.successful_count }}</p>
                            </div>
                            <div class="rounded-lg border border-cyan-100/10 bg-slate-900/35 px-3 py-2">
                                <p class="text-[11px] uppercase tracking-wide text-slate-500">Fallidos</p>
                                <p class="mt-1 text-slate-100">{{ dispatch.failed_count }}</p>
                            </div>
                        </div>
                    </article>
                    <div v-if="!recent_dispatches.length" class="rounded-xl border border-cyan-100/15 bg-slate-950/35 px-4 py-6 text-center text-sm text-slate-300">
                        Todavia no hay envios registrados.
                    </div>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
