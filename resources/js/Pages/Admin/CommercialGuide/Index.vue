<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    quick_rules: { type: Array, default: () => [] },
    sections: { type: Array, default: () => [] },
    whatsapp_templates: { type: Array, default: () => [] },
});

const copyState = reactive({});

const fallbackCopy = async (value) => {
    const textarea = document.createElement('textarea');
    textarea.value = value;
    textarea.setAttribute('readonly', 'readonly');
    textarea.style.position = 'absolute';
    textarea.style.left = '-9999px';
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand('copy');
    document.body.removeChild(textarea);
};

const copyTemplate = async (key, value) => {
    try {
        if (navigator?.clipboard?.writeText) {
            await navigator.clipboard.writeText(value);
        } else {
            await fallbackCopy(value);
        }

        copyState[key] = 'copied';
        window.setTimeout(() => {
            copyState[key] = '';
        }, 1800);
    } catch (error) {
        copyState[key] = 'error';
        window.setTimeout(() => {
            copyState[key] = '';
        }, 2200);
    }
};
</script>

<template>
    <Head title="Guia Comercial" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-2xl font-bold text-slate-100">Guia comercial</h2>
                <p class="mt-1 text-sm text-slate-300/80">Referencia interna para superadmin con explicaciones de planes y respuestas listas para WhatsApp.</p>
            </div>
        </template>

        <div class="grid gap-6">
            <section class="grid gap-4 md:grid-cols-3">
                <article
                    v-for="rule in quick_rules"
                    :key="rule"
                    class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-sm backdrop-blur"
                >
                    <p class="text-sm leading-6 text-slate-100">{{ rule }}</p>
                </article>
            </section>

            <section class="grid gap-4">
                <article
                    v-for="section in sections"
                    :key="section.title"
                    class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-sm backdrop-blur"
                >
                    <div class="max-w-3xl">
                        <h3 class="text-lg font-semibold text-slate-100">{{ section.title }}</h3>
                        <p class="mt-1 text-sm leading-6 text-slate-300/80">{{ section.description }}</p>
                    </div>

                    <div class="mt-4 grid gap-4 lg:grid-cols-3">
                        <article
                            v-for="item in section.items"
                            :key="item.title"
                            class="rounded-2xl border border-cyan-100/15 bg-slate-950/35 p-4"
                        >
                            <h4 class="text-base font-semibold text-slate-100">{{ item.title }}</h4>
                            <p class="mt-2 text-xs uppercase tracking-wide text-cyan-100/70">Ideal para</p>
                            <p class="mt-1 text-sm text-slate-200">{{ item.ideal_for }}</p>
                            <p class="mt-3 text-sm leading-6 text-slate-300">{{ item.summary }}</p>

                            <ul class="mt-4 space-y-2 text-sm text-slate-200/90">
                                <li
                                    v-for="highlight in item.highlights"
                                    :key="highlight"
                                    class="flex items-start gap-3"
                                >
                                    <span class="mt-2 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-cyan-300" />
                                    <span>{{ highlight }}</span>
                                </li>
                            </ul>
                        </article>
                    </div>
                </article>
            </section>

            <section class="rounded-2xl border border-cyan-100/20 bg-slate-900/45 p-5 shadow-sm backdrop-blur">
                <div class="max-w-3xl">
                    <h3 class="text-lg font-semibold text-slate-100">Respuestas para WhatsApp</h3>
                    <p class="mt-1 text-sm leading-6 text-slate-300/80">Textos listos para copiar y adaptar segun cada conversacion comercial.</p>
                </div>

                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    <article
                        v-for="template in whatsapp_templates"
                        :key="template.key"
                        class="rounded-2xl border border-cyan-100/15 bg-slate-950/35 p-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h4 class="text-base font-semibold text-slate-100">{{ template.title }}</h4>
                                <p class="mt-1 text-sm text-slate-300/80">{{ template.description }}</p>
                            </div>

                            <button
                                type="button"
                                class="rounded-lg border border-cyan-200/30 bg-cyan-300/10 px-3 py-2 text-xs font-semibold text-cyan-100 transition hover:bg-cyan-300/20"
                                @click="copyTemplate(template.key, template.message)"
                            >
                                {{
                                    copyState[template.key] === 'copied'
                                        ? 'Copiado'
                                        : copyState[template.key] === 'error'
                                            ? 'Error'
                                            : 'Copiar'
                                }}
                            </button>
                        </div>

                        <textarea
                            :value="template.message"
                            rows="8"
                            readonly
                            class="mt-4 w-full rounded-xl border border-cyan-100/15 bg-slate-900/60 p-3 text-sm leading-6 text-slate-100"
                        />
                    </article>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
