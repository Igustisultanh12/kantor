<script setup> import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import SindenBackdrop from '@/Components/SindenBackdrop.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="sinden-shell flex min-h-screen flex-col items-center pt-6 text-slate-700 sm:justify-center sm:pt-0"
    >
        <SindenBackdrop />

        <div>
            <Link href="/">
                <ApplicationLogo class="h-20 w-20 fill-current text-slate-500" />
            </Link>
        </div>

        <div
            class="mt-6 w-full overflow-hidden rounded-3xl border border-white/70 bg-white px-6 py-6 shadow-xl sm:max-w-md"
        >
            <slot />
        </div>
    </div>
</template>
