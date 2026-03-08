<script setup>
import { Head, Link } from '@inertiajs/vue3';

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
                        Sistema de gestion comercial listo para usar
                    </h2>
                    <p class="mt-5 max-w-2xl text-base text-slate-200/90 md:text-lg">
                        Implementacion, configuracion inicial y puesta en marcha para ventas, compras y stock.
                        La carga masiva de productos puede contratarse como servicio opcional.
                    </p>

                    <div class="mt-8 grid gap-4 sm:grid-cols-2">
                        <article class="box">
                            <h3 class="title text-lg font-semibold">Puesta en marcha</h3>
                            <p class="mt-2 text-sm text-slate-200/85">Configuracion, parametros iniciales y capacitacion.</p>
                        </article>
                        <article class="box">
                            <h3 class="title text-lg font-semibold">Carga opcional</h3>
                            <p class="mt-2 text-sm text-slate-200/85">Si queres, cargo tus productos para que arranques mas rapido.</p>
                        </article>
                    </div>

                    <article class="box mt-4">
                        <h3 class="title text-lg font-semibold">Que incluye hoy</h3>
                        <ul class="mt-3 space-y-2 text-sm text-slate-200/90">
                            <li class="flex items-start gap-2"><span class="dot" />Gestion de ventas, compras y stock.</li>
                            <li class="flex items-start gap-2"><span class="dot" />Control de productos y proveedores.</li>
                            <li class="flex items-start gap-2"><span class="dot" />Reportes operativos para seguimiento diario.</li>
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
                    <p class="title text-sm uppercase tracking-[0.2em] text-cyan-100/90">Costos en ARS</p>

                    <div class="mt-5 rounded-2xl bg-white/10 p-5 ring-1 ring-white/15">
                        <p class="text-sm text-cyan-100/90">Implementacion base</p>
                        <p class="title mt-1 text-2xl font-extrabold text-white">ARS 150.000</p>
                        <p class="mt-2 text-xs text-slate-200/90">
                            Incluye configuracion inicial, puesta en marcha y capacitacion para uso del sistema.
                        </p>
                    </div>

                    <div class="mt-4 rounded-2xl bg-white/10 p-5 ring-1 ring-white/15">
                        <p class="text-sm text-cyan-100/90">Carga de productos (opcional)</p>
                        <p class="title mt-1 text-xl font-extrabold text-white">Desde ARS 1.000 por producto</p>
                        <p class="mt-2 text-xs text-slate-200/90">
                            Referencia: entre ARS 200.000 y ARS 300.000 cuando se cargan grandes volumenes.
                        </p>
                    </div>

                    <div class="mt-4 rounded-2xl bg-cyan-300/15 p-5 ring-1 ring-cyan-100/30">
                        <p class="text-sm text-cyan-100/90">Mensual</p>
                        <p class="title mt-1 text-2xl font-extrabold text-white">ARS 30.000</p>
                        <p class="mt-2 text-xs text-slate-200/90">Mantenimiento, soporte y acompanamiento operativo.</p>
                    </div>

                    <div class="mt-4 rounded-2xl bg-emerald-300/15 p-5 ring-1 ring-emerald-100/30">
                        <p class="text-sm text-emerald-100">Demo gratuita</p>
                        <p class="title mt-1 text-xl font-extrabold text-white">7 dias sin costo</p>
                        <p class="mt-2 text-xs text-slate-200/90">
                            Se cargan hasta 50 productos (carga realizada por mi) e incluye capacitacion de uso.
                        </p>
                    </div>

                    <div class="mt-4 rounded-2xl bg-cyan-300/12 p-5 ring-1 ring-cyan-100/25">
                        <p class="text-sm text-cyan-100/90">Proximas actualizaciones</p>
                        <ul class="mt-2 space-y-1 text-xs text-slate-200/90">
                            <li class="flex items-start gap-2"><span class="dot" />Vencimientos de productos.</li>
                            <li class="flex items-start gap-2"><span class="dot" />Notificaciones via mail.</li>
                            <li class="flex items-start gap-2"><span class="dot" />Soporte multi sucursal.</li>
                        </ul>
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
