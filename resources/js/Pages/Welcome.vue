<script setup>
import { Head, Link } from '@inertiajs/vue3';

const whatsappUrl =
    'https://wa.me/542612476927?text=Hola%2C%20vi%20la%20landing%20del%20sistema%20de%20gestion%20comercial%20y%20quiero%20consultar%20por%20la%20implementacion%20y%20los%20planes%20disponibles.';

const pricingSections = [
    {
        title: 'Instalacion inicial',
        description: 'Opciones de arranque para distintos tamanos de comercio y nivel de acompanamiento.',
        plans: [
            {
                title: 'Plan Esencial',
                subtitle: 'Pago unico',
                description: 'Pensado para negocios que necesitan empezar rapido con una estructura simple.',
                price: '150.000',
                priceLabel: 'ARS',
                featured: true,
                features: [
                    'Instalacion + Configuracion',
                    'Capacitacion completa',
                    'Hasta 100 productos incluidos',
                    'Soporte inicial',
                ],
            },
            {
                title: 'Plan Plus',
                subtitle: 'Pago unico',
                description: 'Mayor acompanamiento para catalogos mas amplios y una puesta en marcha mas completa.',
                price: '220.000',
                priceLabel: 'ARS',
                features: [
                    'Implementacion completa',
                    'Hasta 250 productos incluidos',
                    'Capacitacion por sectores',
                    'Ajustes iniciales de trabajo',
                ],
            },
            {
                title: 'Plan Express',
                subtitle: 'Pago unico',
                description: 'Version agil para negocios chicos que quieren salir a operar con una base minima.',
                price: '95.000',
                priceLabel: 'ARS',
                features: [
                    'Configuracion base',
                    'Capacitacion breve',
                    'Hasta 30 productos incluidos',
                    'Soporte de arranque',
                ],
            },
        ],
    },
    {
        title: 'Carga de productos',
        description: 'Alternativas para delegar la carga inicial o ampliar el catalogo cuando tu negocio lo necesite.',
        plans: [
            {
                title: 'Carga por unidad',
                subtitle: 'Flexible',
                description: 'Ideal para sumar productos puntuales sin contratar un paquete completo.',
                price: '$500',
                priceSuffix: 'por producto',
                features: ['Desde el producto 101', 'Carga manual individual', 'Revision basica de datos'],
            },
            {
                title: 'Pack 100 productos',
                subtitle: 'Pago unico',
                description: 'Una opcion comoda para ampliar el catalogo con un volumen medio de trabajo.',
                price: '45.000',
                priceLabel: 'ARS',
                features: ['Hasta 100 productos', 'Carga estructurada', 'Validacion general del listado'],
            },
            {
                title: 'Pack 250 productos',
                subtitle: 'Pago unico',
                description: 'Recomendado para comercios con catalogos amplios que quieren acelerar la puesta en marcha.',
                price: '95.000',
                priceLabel: 'ARS',
                features: ['Hasta 250 productos', 'Carga masiva asistida', 'Revision de categorias y precios'],
            },
        ],
    },
    {
        title: 'Mantenimiento mensual',
        description: 'Niveles de soporte para sostener la operacion, resolver incidentes y sumar mejoras menores.',
        plans: [
            {
                title: 'Plan Basico',
                subtitle: 'Mensual',
                description: 'Cobertura simple para mantener el sistema estable y con acompanamiento continuo.',
                price: '25.000',
                priceSuffix: '/ mes',
                features: ['Soporte continuo', 'Correccion de errores', 'Mejoras menores', 'Actualizaciones'],
            },
            {
                title: 'Plan Operativo',
                subtitle: 'Mensual',
                description: 'Mas seguimiento para comercios con mayor uso diario y necesidad de respuesta frecuente.',
                price: '45.000',
                priceSuffix: '/ mes',
                features: [
                    'Todo lo del plan basico',
                    'Prioridad media en soporte',
                    'Revision mensual operativa',
                    'Descuento en funcionalidades personalizadas segun requerimiento',
                ],
            },
            {
                title: 'Plan Prioritario',
                subtitle: 'Mensual',
                description: 'Acompanamiento completo para negocios que necesitan evolucion constante y soporte cercano.',
                price: '60.000',
                priceSuffix: '/ mes',
                features: [
                    'Prioridad alta en soporte',
                    'Ajustes menores incluidos',
                    'Seguimiento mas cercano',
                    'Funcionalidades personalizadas totalmente incluidas',
                ],
            },
        ],
    },
];

const pricingNotes = [
    'Los valores de implementacion cambian segun el alcance inicial y el volumen de productos.',
    'La carga de productos puede contratarse por unidad o por paquetes segun necesidad.',
    'No incluye desarrollos personalizados ni integraciones a medida.',
    'Desde el plan mensual de ARS 45.000 se contemplan beneficios sobre funcionalidades personalizadas.',
];

const productHighlights = [
    {
        title: 'Operacion diaria',
        description: 'Ventas, compras y stock por comercio con impacto automatico en cada movimiento.',
    },
    {
        title: 'Control fino de inventario',
        description: 'Productos, categorias, proveedores, lotes y vencimientos en un mismo flujo.',
    },
    {
        title: 'Seguimiento y alertas',
        description: 'Dashboard operativo y correos configurables para stock bajo, agotados y vencimientos.',
    },
];

const currentCapabilities = [
    'Dashboard con metricas operativas y alertas visibles por comercio.',
    'Gestion de categorias, productos y proveedores.',
    'Ventas y compras con actualizacion automatica de stock.',
    'Control de lotes con proximos vencimientos y productos vencidos.',
    'Usuarios internos con roles admin y staff.',
    'Notificaciones por mail con destinatarios, horarios y pausado manual.',
];

const notificationHighlights = [
    'Resumen por mail de stock bajo, agotado y vencimientos.',
    'Horario configurable por comercio para evitar avisos fuera de turno.',
    'Envio a email principal, admins y correos adicionales.',
    'Historial reciente de envios desde la configuracion del comercio.',
];

const implementationPillars = [
    'Puesta en marcha guiada y capacitacion inicial.',
    'Carga de productos opcional si el comercio quiere delegarla.',
    'Base preparada para operar en Laravel Cloud con scheduler y colas.',
];

defineProps({
    canLogin: { type: Boolean, default: false },
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

                <nav v-if="canLogin">
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

            <main class="my-auto grid gap-6 py-10 lg:grid-cols-3">
                <section class="entry panel lg:col-span-2" style="animation-delay: 80ms">
                    <p class="title text-sm uppercase tracking-[0.2em] text-cyan-200/90">Que ofrezco</p>
                    <h2 class="title mt-3 text-3xl font-bold leading-tight md:text-5xl">
                        Ventas, stock y alertas operativas en un solo sistema
                    </h2>
                    <p class="mt-5 max-w-2xl text-base text-slate-200/90 md:text-lg">
                        El MVP ya resuelve la operacion diaria del comercio: productos, categorias, proveedores,
                        compras, ventas, dashboard, usuarios internos y notificaciones por mail para stock y
                        vencimientos. La carga masiva de productos sigue disponible como servicio opcional.
                    </p>

                    <div class="mt-8 grid gap-4 md:grid-cols-3">
                        <article v-for="highlight in productHighlights" :key="highlight.title" class="box">
                            <h3 class="title text-lg font-semibold">{{ highlight.title }}</h3>
                            <p class="mt-2 text-sm text-slate-200/85">{{ highlight.description }}</p>
                        </article>
                    </div>

                    <article class="box mt-4">
                        <h3 class="title text-lg font-semibold">Que incluye hoy</h3>
                        <ul class="mt-3 space-y-2 text-sm text-slate-200/90">
                            <li v-for="capability in currentCapabilities" :key="capability" class="flex items-start gap-2">
                                <span class="dot" />
                                <span>{{ capability }}</span>
                            </li>
                        </ul>
                    </article>

                    <article class="box mt-4">
                        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                            <div>
                                <h3 class="title text-lg font-semibold">Alertas operativas listas</h3>
                                <p class="mt-2 text-sm text-slate-200/85">
                                    El sistema ya puede avisar automaticamente cuando un comercio necesita reaccionar.
                                </p>
                            </div>
                            <span class="tag">Mail configurable</span>
                        </div>

                        <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                            <li
                                v-for="item in notificationHighlights"
                                :key="item"
                                class="rounded-2xl border border-cyan-100/15 bg-slate-950/35 px-4 py-3 text-sm text-slate-200/90"
                            >
                                {{ item }}
                            </li>
                        </ul>
                    </article>

                    <article class="box mt-4">
                        <h3 class="title text-lg font-semibold">Alcance</h3>
                        <p class="mt-2 text-sm text-slate-200/85">Ideal para negocios minoristas y de mostrador:</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span class="tag">Kioscos</span>
                            <span class="tag">Tiendas</span>
                            <span class="tag">Ferreterias</span>
                            <span class="tag">Petshops</span>
                            <span class="tag">Regalerias</span>
                            <span class="tag">Autoservicios</span>
                            <span class="tag">Y mas</span>
                        </div>
                    </article>
                </section>

                <section class="entry panel price" style="animation-delay: 180ms">
                    <p class="title text-sm uppercase tracking-[0.2em] text-cyan-100/90">Propuesta comercial</p>
                    <h3 class="title mt-3 text-2xl font-bold text-white">Implementacion clara, simple y enfocada en operar</h3>
                    <p class="mt-4 text-sm leading-6 text-slate-200/90">
                        Pensado para comercios que necesitan ordenar ventas, stock, vencimientos y seguimiento diario
                        sin entrar en procesos largos ni en una implementacion pesada.
                    </p>

                    <div class="mt-6 space-y-3">
                        <article
                            v-for="(pillar, index) in implementationPillars"
                            :key="pillar"
                            class="rounded-2xl p-5 ring-1"
                            :class="index === implementationPillars.length - 1
                                ? 'bg-cyan-300/15 ring-cyan-100/30'
                                : 'bg-white/10 ring-white/15'"
                        >
                            <p class="text-sm text-cyan-100/90">Pilar {{ index + 1 }}</p>
                            <p class="mt-2 text-sm text-slate-200/90">
                                {{ pillar }}
                            </p>
                        </article>
                    </div>

                    <a
                        :href="whatsappUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="mt-6 inline-flex rounded-full bg-cyan-300 px-5 py-3 text-sm font-semibold text-slate-900 transition hover:bg-cyan-200"
                    >
                        Consultar por WhatsApp
                    </a>
                </section>
            </main>

            <section class="entry mx-auto mt-4 w-full max-w-6xl px-4 pb-10" style="animation-delay: 260ms">
                <div class="rounded-[2rem] border border-white/10 bg-white/95 px-6 py-10 text-slate-900 shadow-2xl shadow-slate-950/20 md:px-10">
                    <div class="mx-auto max-w-3xl text-center">
                        <p class="title text-sm uppercase tracking-[0.25em] text-cyan-700">Precios</p>
                        <h2 class="title mt-3 text-3xl font-bold text-slate-950 md:text-4xl">
                            Planes del Sistema de Gestion Comercial
                        </h2>
                        <p class="mt-4 text-base text-slate-600 md:text-lg">
                            Solucion para controlar ventas, compras, stock, vencimientos y alertas en tu comercio
                        </p>
                    </div>

                    <div class="mt-10 space-y-10">
                        <section v-for="section in pricingSections" :key="section.title">
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
                                            <p
                                                v-if="plan.priceLabel"
                                                class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500"
                                            >
                                                {{ plan.priceLabel }}
                                            </p>
                                            <p class="title mt-1 text-3xl font-semibold text-slate-950">
                                                {{ plan.price }}
                                                <span v-if="plan.priceSuffix" class="ml-1 text-base font-medium text-slate-500">
                                                    {{ plan.priceSuffix }}
                                                </span>
                                            </p>
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

                    <div class="mt-8 rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4">
                        <ul class="space-y-2 text-sm text-slate-500">
                            <li v-for="note in pricingNotes" :key="note" class="flex items-start gap-3">
                                <span class="mt-2 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-slate-400" />
                                <span>{{ note }}</span>
                            </li>
                        </ul>
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

.tag {
    border-radius: 9999px;
    border: 1px solid rgba(186, 230, 253, 0.35);
    background: rgba(56, 189, 248, 0.12);
    padding: 0.2rem 0.65rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: rgba(224, 242, 254, 0.95);
}

.dot {
    margin-top: 0.35rem;
    height: 0.45rem;
    width: 0.45rem;
    border-radius: 9999px;
    background: rgb(103, 232, 249);
    flex-shrink: 0;
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
