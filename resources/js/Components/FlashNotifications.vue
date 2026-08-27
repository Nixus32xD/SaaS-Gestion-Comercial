<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const STORAGE_KEY = 'gestor-comercial:flash-notification';
const page = usePage();
const notification = ref(null);
let dismissTimeout = null;

const dismiss = () => {
    notification.value = null;

    if (dismissTimeout !== null) {
        window.clearTimeout(dismissTimeout);
        dismissTimeout = null;
    }
};

const show = (value) => {
    if (!value?.message) return;

    notification.value = value;

    if (dismissTimeout !== null) {
        window.clearTimeout(dismissTimeout);
    }

    dismissTimeout = window.setTimeout(dismiss, 6000);
};

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.error) {
            show({ tone: 'error', title: 'No se pudo completar', message: flash.error });
        } else if (flash?.warning) {
            show({ tone: 'warning', title: 'Atención', message: flash.warning });
        } else if (flash?.success) {
            show({ tone: 'success', title: 'Operación completada', message: flash.success });
        }
    },
    { immediate: true, deep: true },
);

onMounted(() => {
    const storedNotification = window.sessionStorage.getItem(STORAGE_KEY);

    if (storedNotification === null) return;

    window.sessionStorage.removeItem(STORAGE_KEY);

    try {
        show(JSON.parse(storedNotification));
    } catch {
        // Ignora valores inválidos de una sesión anterior.
    }
});

onBeforeUnmount(() => {
    if (dismissTimeout !== null) {
        window.clearTimeout(dismissTimeout);
    }
});
</script>

<template>
    <Teleport to="body">
        <Transition name="flash-notification">
            <section
                v-if="notification"
                class="fixed right-4 top-4 z-[60] flex w-[min(26rem,calc(100vw-2rem))] gap-3 rounded-2xl border p-4 shadow-2xl backdrop-blur-xl"
                :class="notification.tone === 'error'
                    ? 'border-rose-200/45 bg-rose-950/95 text-rose-50'
                    : (notification.tone === 'warning'
                        ? 'border-amber-200/45 bg-amber-950/95 text-amber-50'
                        : 'border-emerald-200/45 bg-emerald-950/95 text-emerald-50')"
                role="status"
                aria-live="polite"
            >
                <div
                    class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
                    :class="notification.tone === 'error'
                        ? 'bg-rose-400/20 text-rose-100'
                        : (notification.tone === 'warning'
                            ? 'bg-amber-400/20 text-amber-100'
                            : 'bg-emerald-400/20 text-emerald-100')"
                >
                    <svg v-if="notification.tone === 'success'" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" />
                    </svg>
                    <svg v-else class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M10.3 3.9 2.7 17a2 2 0 0 0 1.73 3h15.14A2 2 0 0 0 21.3 17L13.7 3.9a2 2 0 0 0-3.4 0Z" />
                    </svg>
                </div>

                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold">{{ notification.title }}</p>
                    <p class="mt-1 text-sm opacity-90">{{ notification.message }}</p>
                </div>

                <button type="button" class="rounded-lg p-1 opacity-75 transition hover:bg-white/10 hover:opacity-100" aria-label="Cerrar notificación" @click="dismiss">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" />
                    </svg>
                </button>
            </section>
        </Transition>
    </Teleport>
</template>

<style scoped>
.flash-notification-enter-active,
.flash-notification-leave-active {
    transition: opacity 180ms ease, transform 180ms ease;
}

.flash-notification-enter-from,
.flash-notification-leave-to {
    opacity: 0;
    transform: translateY(-0.75rem) scale(0.98);
}
</style>
