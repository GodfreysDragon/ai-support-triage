<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';

const page = usePage();
</script>

<template>
    <Head title="Welcome" />

    <div
        class="flex min-h-screen flex-col items-center justify-center bg-background px-4 py-16 text-foreground"
    >
        <main class="flex max-w-2xl flex-col items-center text-center">
            <AppLogoIcon class="size-12 fill-current" />

            <h1 class="mt-6 text-4xl font-semibold tracking-tight sm:text-5xl">
                {{ page.props.name }}
            </h1>

            <p
                class="mt-4 text-lg text-balance text-muted-foreground sm:text-xl"
            >
                Never fall behind on support again.
            </p>

            <div class="mt-10 flex flex-wrap justify-center gap-3">
                <Button v-if="page.props.auth.user" as-child size="lg">
                    <Link :href="dashboard()">Go to dashboard</Link>
                </Button>
                <template v-else>
                    <Button as-child size="lg">
                        <Link :href="register()">Get started</Link>
                    </Button>
                    <Button as-child size="lg" variant="outline">
                        <Link :href="login()">Log in</Link>
                    </Button>
                </template>
            </div>
        </main>
    </div>
</template>
