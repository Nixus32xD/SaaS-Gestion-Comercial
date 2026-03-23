<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <Head title="Recuperar contrasena" />

    <div class="auth-bg min-h-screen text-slate-100">
        <div class="mx-auto grid min-h-screen w-full max-w-6xl grid-cols-1 items-center gap-8 px-6 py-10 lg:grid-cols-2">
            <section class="entry hidden rounded-3xl border border-cyan-200/20 bg-slate-900/30 p-8 lg:block">
                <p class="title text-xs uppercase tracking-[0.3em] text-cyan-200/80">Recuperacion de acceso</p>
                <h1 class="title mt-4 text-4xl font-bold leading-tight">
                    Recupera tu cuenta sin perder el ritmo de trabajo
                </h1>
                <p class="mt-4 max-w-xl text-slate-200/85">
                    Te enviamos un enlace para elegir una nueva contrasena y volver a ingresar al sistema.
                </p>

                <div class="mt-8 grid gap-3">
                    <div class="item">
                        <span class="dot" />
                        <p>Ingresa el correo con el que accedes a tu comercio.</p>
                    </div>
                    <div class="item">
                        <span class="dot" />
                        <p>Revisa tu bandeja de entrada y correo no deseado.</p>
                    </div>
                    <div class="item">
                        <span class="dot" />
                        <p>Desde el enlace podras definir una nueva contrasena.</p>
                    </div>
                </div>
            </section>

            <section class="entry panel mx-auto w-full max-w-md">
                <Link href="/login" class="inline-flex items-center gap-2 rounded-full border border-cyan-200/30 px-3 py-1 text-xs font-semibold text-cyan-100 transition hover:bg-cyan-200/10">
                    Volver al login
                </Link>

                <h2 class="title mt-5 text-3xl font-bold text-white">Olvide mi contrasena</h2>
                <p class="mt-2 text-sm text-slate-200/80">Ingresa tu email y te enviaremos un enlace para restablecerla.</p>

                <div v-if="status" class="mt-4 rounded-xl border border-emerald-300/40 bg-emerald-400/15 px-3 py-2 text-sm text-emerald-100">
                    {{ status }}
                </div>

                <form class="mt-6 space-y-4" @submit.prevent="submit">
                    <div>
                        <label for="email" class="mb-1 block text-sm font-semibold text-slate-200">Email</label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            required
                            autofocus
                            autocomplete="username"
                            class="field"
                        />
                        <p v-if="form.errors.email" class="mt-1 text-xs text-rose-200">{{ form.errors.email }}</p>
                    </div>

                    <div class="flex items-center justify-end pt-2">
                        <button
                            type="submit"
                            class="title rounded-lg bg-cyan-300 px-5 py-2.5 text-sm font-bold text-slate-900 transition hover:bg-cyan-200 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? 'Enviando...' : 'Enviar enlace' }}
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Space+Grotesk:wght@500;700&display=swap');

.auth-bg {
    background:
        radial-gradient(circle at 85% 15%, rgba(56, 189, 248, 0.26), transparent 42%),
        radial-gradient(circle at 10% 70%, rgba(15, 23, 42, 0.45), transparent 38%),
        linear-gradient(120deg, #05264e 0%, #0f172a 45%, #101f4d 100%);
}

.title {
    font-family: 'Space Grotesk', sans-serif;
}

.auth-bg,
.panel,
.item {
    font-family: 'Manrope', sans-serif;
}

.panel {
    border-radius: 1.25rem;
    border: 1px solid rgba(186, 230, 253, 0.25);
    background: rgba(15, 23, 42, 0.55);
    padding: 1.5rem;
    box-shadow: 0 20px 45px rgba(8, 47, 73, 0.36);
}

.field {
    width: 100%;
    border-radius: 0.7rem;
    border: 1px solid rgba(148, 163, 184, 0.6);
    background: rgba(15, 23, 42, 0.4);
    color: rgb(248, 250, 252);
    padding: 0.65rem 0.8rem;
}

.field:focus {
    border-color: rgba(103, 232, 249, 0.8);
    outline: none;
    box-shadow: 0 0 0 3px rgba(34, 211, 238, 0.2);
}

.item {
    display: flex;
    gap: 0.6rem;
    align-items: flex-start;
    border-radius: 0.8rem;
    border: 1px solid rgba(186, 230, 253, 0.16);
    background: rgba(15, 23, 42, 0.42);
    padding: 0.75rem;
}

.dot {
    margin-top: 0.35rem;
    height: 0.45rem;
    width: 0.45rem;
    border-radius: 9999px;
    background: rgb(103, 232, 249);
    flex-shrink: 0;
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
