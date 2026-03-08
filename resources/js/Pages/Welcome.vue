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
                    </p>

                    <div class="mt-8 grid gap-4 sm:grid-cols-2">
                        <article class="box">
                            <h3 class="title text-lg font-semibold">Implementacion inicial</h3>
                            <p class="mt-2 text-sm text-slate-200/85">Carga base y parametrizacion del sistema.</p>
                        </article>
                        <article class="box">
                            <h3 class="title text-lg font-semibold">Soporte mensual</h3>
                            <p class="mt-2 text-sm text-slate-200/85">Acompanamiento continuo para operar sin fricciones.</p>
                        </article>
                    </div>
                </section>

                <section class="entry panel price" style="animation-delay: 180ms">
                    <p class="title text-sm uppercase tracking-[0.2em] text-cyan-100/90">Costos en ARS</p>

                    <div class="mt-5 rounded-2xl bg-white/10 p-5 ring-1 ring-white/15">
                        <p class="text-sm text-cyan-100/90">Instalacion</p>
                        <p class="title mt-1 text-2xl font-extrabold text-white">ARS 200.000 a ARS 300.000</p>
                        <p class="mt-2 text-xs text-slate-200/90">
                            Depende de la cantidad de productos que quieras que cargue inicialmente.
                        </p>
                    </div>

                    <div class="mt-4 rounded-2xl bg-cyan-300/15 p-5 ring-1 ring-cyan-100/30">
                        <p class="text-sm text-cyan-100/90">Mensual</p>
                        <p class="title mt-1 text-2xl font-extrabold text-white">ARS 30.000</p>
                        <p class="mt-2 text-xs text-slate-200/90">Mantenimiento y acompanamiento operativo.</p>
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
