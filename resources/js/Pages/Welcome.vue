<script setup>
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    canLogin: { type: Boolean, default: false },
    whatsappUrl: { type: String, default: '' },
    pricingSections: { type: Array, default: () => [] },
    heroHighlights: { type: Array, default: () => [] },
    coreFeatures: { type: Array, default: () => [] },
    planSummaries: { type: Array, default: () => [] },
    pricingNotes: { type: Array, default: () => [] },
    businessTypes: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Gestor Comercial SaaS" />

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

                <section class="entry panel price" style="animation-delay: 180ms">
                    <p class="eyebrow text-sm uppercase tracking-[0.2em] text-cyan-100/90">Propuesta comercial</p>
                    <h3 class="title mt-3 text-2xl font-bold text-white">Implementacion simple y acompanada</h3>
                    <p class="mt-4 text-sm leading-6 text-slate-200/90">
                        Tres formas de contratar el servicio segun la etapa en la que este tu comercio.
                    </p>

                    <div class="mt-6 space-y-3">
                        <article v-for="plan in props.planSummaries" :key="plan.title" class="summary-card">
                            <p class="text-sm text-cyan-100/85">{{ plan.title }}</p>
                            <h4 class="title mt-2 text-2xl font-bold text-white">{{ plan.price }}</h4>
                            <p class="mt-2 text-sm leading-6 text-slate-200/90">{{ plan.description }}</p>
                        </article>
                    </div>

                    <div class="mt-6 rounded-2xl border border-cyan-100/15 bg-slate-950/30 px-4 py-4">
                        <ul class="space-y-2 text-sm text-slate-300">
                            <li v-for="note in props.pricingNotes" :key="note" class="flex items-start gap-3">
                                <span class="mt-2 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-cyan-300" />
                                <span>{{ note }}</span>
                            </li>
                        </ul>
                    </div>

                    <div class="cta-block mt-6">
                        <p class="title text-lg font-semibold text-white">Listo para avanzar</p>
                        <p class="mt-2 text-sm leading-6 text-slate-200/90">
                            Si queres implementarlo o definir el plan adecuado, te respondo por WhatsApp.
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

            <section class="entry mx-auto mt-2 w-full max-w-6xl px-4 pb-10" style="animation-delay: 260ms">
                <div class="rounded-[2rem] border border-white/10 bg-white/95 px-6 py-10 text-slate-900 shadow-2xl shadow-slate-950/20 md:px-10">
                    <div class="mx-auto max-w-3xl text-center">
                        <p class="eyebrow text-sm uppercase tracking-[0.25em] text-cyan-700">Grilla de precios</p>
                        <h2 class="title mt-3 text-3xl font-bold text-slate-950 md:text-4xl">
                            Planes del Sistema de Gestion Comercial
                        </h2>
                        <p class="mt-4 text-base leading-7 text-slate-600 md:text-lg">
                            Si queres ver el detalle completo, aca tenes la grilla por instalacion, carga y mantenimiento.
                        </p>
                    </div>

                    <div class="mt-10 space-y-10">
                        <section v-for="section in props.pricingSections" :key="section.title">
                            <div class="max-w-2xl">
                                <h3 class="title text-2xl font-bold text-slate-950">{{ section.title }}</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-600">
                                    {{ section.description }}
                                </p>
                            </div>

                            <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-3">
                                <article
                                    v-for="plan in section.plans"
                                    :key="`${section.title}-${plan.title}`"
                                    class="flex h-full flex-col rounded-2xl border border-slate-200 bg-white p-6 shadow-lg"
                                    :class="plan.featured ? 'ring-2 ring-cyan-500/20' : ''"
                                >
                                    <div class="flex-1">
                                        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-cyan-700">
                                            {{ plan.subtitle }}
                                        </p>
                                        <h4 class="title mt-3 text-2xl font-bold text-slate-950">{{ plan.title }}</h4>
                                        <p class="mt-3 text-sm leading-6 text-slate-600">{{ plan.description }}</p>

                                        <div class="mt-6">
                                            <div class="flex flex-wrap items-baseline gap-2">
                                                <span
                                                    v-if="plan.priceLabel"
                                                    class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500"
                                                >
                                                    {{ plan.priceLabel }}
                                                </span>
                                                <p class="title text-3xl font-semibold text-slate-950">
                                                    {{ plan.price }}
                                                </p>
                                                <span v-if="plan.priceSuffix" class="text-base font-medium text-slate-500">
                                                    {{ plan.priceSuffix }}
                                                </span>
                                            </div>
                                        </div>

                                        <ul v-if="plan.features.length" class="mt-6 space-y-3 text-sm text-slate-700">
                                            <li v-for="feature in plan.features" :key="feature" class="flex items-start gap-3">
                                                <span class="mt-2 h-2 w-2 flex-shrink-0 rounded-full bg-cyan-500" />
                                                <span>{{ feature }}</span>
                                            </li>
                                        </ul>
                                    </div>
                                </article>
                            </div>
                        </section>
                    </div>
                </div>
            </section>
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

.price {
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
