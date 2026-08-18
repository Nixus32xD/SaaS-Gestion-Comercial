<script setup>
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    canLogin: { type: Boolean, default: false },
    seo: { type: Object, default: () => ({}) },
    whatsappUrl: { type: String, default: '' },
    heroHighlights: { type: Array, default: () => [] },
    coreFeatures: { type: Array, default: () => [] },
    planSummaries: { type: Array, default: () => [] },
    businessTypes: { type: Array, default: () => [] },
});
</script>

<template>
    <Head :title="props.seo.title || 'Gestor Comercial SaaS'" />

    <div class="landing text-slate-100">
        <div class="mx-auto flex min-h-screen w-full max-w-6xl flex-col px-6 py-8 md:px-10">
            <header class="entry flex items-center justify-between">
                <div>
                    <p class="title text-xs uppercase tracking-[0.3em] text-cyan-200/80">Servicio SaaS</p>
                    <h1 class="title mt-2 text-xl font-bold md:text-2xl">Gestor Comercial</h1>
                </div>

                <nav v-if="props.canLogin">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="$page.props.auth?.is_super_admin ? route('admin.businesses.index') : route('dashboard')"
                        class="rounded-full bg-cyan-300 px-4 py-2 text-sm font-semibold text-slate-900 transition hover:bg-cyan-200"
                    >
                        {{ $page.props.auth?.is_super_admin ? 'Ir a comercios' : 'Ir al dashboard' }}
                    </Link>
                    <Link
                        v-else
                        :href="route('login')"
                        class="rounded-full border border-cyan-200/60 px-4 py-2 text-sm font-semibold text-cyan-100 transition hover:bg-cyan-200/10"
                    >
                        Iniciar sesion
                    </Link>
                </nav>
            </header>

            <main class="my-auto grid gap-6 py-10 lg:grid-cols-[minmax(0,1.45fr)_minmax(320px,0.95fr)]">
                <section class="entry panel" style="animation-delay: 80ms">
                    <p class="eyebrow text-sm uppercase tracking-[0.2em] text-cyan-200/90">Sistema listo para operar</p>
                    <h2 class="title mt-3 text-3xl font-bold leading-tight md:text-5xl">
                        Ventas, stock y alertas claras para tu comercio
                    </h2>
                    <p class="mt-5 max-w-2xl text-base leading-7 text-slate-200/90 md:text-lg">
                        Un sistema simple para ordenar la operacion diaria: compras, ventas, productos,
                        vencimientos y notificaciones por mail cuando hace falta actuar.
                    </p>

                    <div class="mt-8 grid gap-4 md:grid-cols-3">
                        <article v-for="highlight in props.heroHighlights" :key="highlight.title" class="box subtle-box">
                            <h3 class="title text-lg font-semibold">{{ highlight.title }}</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-200/85">{{ highlight.description }}</p>
                        </article>
                    </div>

                    <article class="box mt-4">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="title text-lg font-semibold">Que incluye hoy</h3>
                                <p class="mt-2 text-sm text-slate-200/80">Lo importante del MVP, sin relleno.</p>
                            </div>
                            <span class="tag">MVP activo</span>
                        </div>

                        <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                            <li v-for="feature in props.coreFeatures" :key="feature" class="notice-card">
                                {{ feature }}
                            </li>
                        </ul>
                    </article>

                    <article class="box mt-4">
                        <h3 class="title text-lg font-semibold">Ideal para</h3>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span v-for="businessType in props.businessTypes" :key="businessType" class="tag">{{ businessType }}</span>
                        </div>
                    </article>
                </section>

                <section class="entry panel offer" style="animation-delay: 180ms">
                    <p class="eyebrow text-sm uppercase tracking-[0.2em] text-cyan-100/90">Acompanamiento</p>
                    <h3 class="title mt-3 text-2xl font-bold text-white">Implementacion segun tu comercio</h3>
                    <p class="mt-4 text-sm leading-6 text-slate-200/90">
                        Definimos el alcance por volumen de productos, carga inicial y soporte necesario.
                    </p>

                    <div class="mt-6 space-y-3">
                        <article v-for="plan in props.planSummaries" :key="plan.title" class="summary-card">
                            <p class="title text-base font-semibold text-white">{{ plan.title }}</p>
                            <h4 v-if="plan.price" class="title mt-2 text-2xl font-bold text-white">{{ plan.price }}</h4>
                            <p class="mt-2 text-sm leading-6 text-slate-200/90">{{ plan.description }}</p>
                            <ul v-if="plan.features?.length" class="mt-3 flex flex-wrap gap-2 text-xs font-semibold text-cyan-50">
                                <li
                                    v-for="feature in plan.features"
                                    :key="feature"
                                    class="rounded-full border border-cyan-100/20 bg-cyan-300/10 px-3 py-1"
                                >
                                    <span>{{ feature }}</span>
                                </li>
                            </ul>
                        </article>
                    </div>

                    <div class="cta-block mt-5">
                        <p class="title text-lg font-semibold text-white">Listo para avanzar</p>
                        <p class="mt-2 text-sm leading-6 text-slate-200/90">
                            Contame tu caso y te propongo una implementacion acorde al comercio.
                        </p>
                        <a
                            :href="props.whatsappUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="mt-4 inline-flex rounded-full bg-cyan-300 px-5 py-3 text-sm font-semibold text-slate-900 transition hover:bg-cyan-200"
                        >
                            Consultar por WhatsApp
                        </a>
                    </div>
                </section>
            </main>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Space+Grotesk:wght@500;700&display=swap');

.landing {
    background:
        radial-gradient(circle at 85% 15%, rgba(56, 189, 248, 0.26), transparent 42%),
        radial-gradient(circle at 10% 70%, rgba(15, 23, 42, 0.45), transparent 38%),
        linear-gradient(120deg, #05264e 0%, #0f172a 45%, #101f4d 100%);
}

.title {
    font-family: 'Space Grotesk', sans-serif;
}

.eyebrow,
.landing,
.panel,
.box {
    font-family: 'Manrope', sans-serif;
}

.panel {
    border-radius: 1.25rem;
    background: rgba(15, 23, 42, 0.45);
    border: 1px solid rgba(186, 230, 253, 0.15);
    padding: 1.5rem;
    backdrop-filter: blur(5px);
}

.box {
    border-radius: 0.9rem;
    border: 1px solid rgba(186, 230, 253, 0.2);
    background: rgba(15, 23, 42, 0.5);
    padding: 1rem;
}

.subtle-box {
    background: rgba(9, 16, 30, 0.35);
}

.notice-card {
    border-radius: 1rem;
    border: 1px solid rgba(186, 230, 253, 0.15);
    background: rgba(2, 6, 23, 0.22);
    padding: 0.95rem 1rem;
    font-size: 0.9rem;
    line-height: 1.6;
    color: rgba(226, 232, 240, 0.92);
}

.summary-card {
    border-radius: 1rem;
    border: 1px solid rgba(186, 230, 253, 0.16);
    background: rgba(255, 255, 255, 0.08);
    padding: 1rem 1.1rem;
}

.tag {
    border-radius: 9999px;
    border: 1px solid rgba(186, 230, 253, 0.35);
    background: rgba(56, 189, 248, 0.12);
    padding: 0.2rem 0.65rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: rgba(224, 242, 254, 0.95);
}

.cta-block {
    border-radius: 1.2rem;
    border: 1px solid rgba(186, 230, 253, 0.22);
    background: linear-gradient(180deg, rgba(8, 47, 73, 0.5) 0%, rgba(15, 23, 42, 0.55) 100%);
    padding: 1.25rem;
}

.offer {
    box-shadow: 0 20px 45px rgba(8, 47, 73, 0.36);
}

.entry {
    animation: fade-up 0.65s ease-out both;
}

@keyframes fade-up {
    from {
        opacity: 0;
        transform: translateY(14px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
