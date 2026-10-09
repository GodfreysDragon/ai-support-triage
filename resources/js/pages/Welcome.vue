<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { dashboard, demo, login, register } from '@/routes';

defineProps<{
    demoEnabled: boolean;
}>();

const page = usePage();
</script>

<template>
    <Head title="Welcome" />

    <div
        class="flex min-h-screen flex-col items-center justify-center bg-background px-4 py-16 text-foreground"
    >
        <main class="flex max-w-2xl flex-col items-center text-center">
            <AppLogoIcon class="size-12 fill-current" aria-hidden="true" />

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
                    <!-- Creates a throwaway account with sample tickets (DemoController). -->
                    <Button v-if="demoEnabled" as-child size="lg">
                        <Link :href="demo()" as="button">Try the demo</Link>
                    </Button>
                    <Button
                        as-child
                        size="lg"
                        :variant="demoEnabled ? 'outline' : 'default'"
                    >
                        <Link :href="register()">Create an account</Link>
                    </Button>
                    <Button as-child size="lg" variant="ghost">
                        <Link :href="login()">Log in</Link>
                    </Button>
                </template>
            </div>

            <p
                v-if="demoEnabled && !page.props.auth.user"
                class="mt-4 text-sm text-muted-foreground"
            >
                No sign-up needed. The demo comes with sample tickets.
            </p>
        </main>
    </div>
</template>
