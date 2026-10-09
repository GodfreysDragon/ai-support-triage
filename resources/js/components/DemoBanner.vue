<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { FlaskConical } from '@lucide/vue';

const page = usePage();
</script>

<template>
    <!-- Shown to "Try the demo" accounts only (users.is_demo). -->
    <div
        v-if="page.props.auth.user?.is_demo"
        role="status"
        class="flex items-center gap-2 border-b bg-muted/50 px-4 py-2 text-sm text-muted-foreground"
    >
        <FlaskConical class="size-4 shrink-0" aria-hidden="true" />
        <span>
            You're exploring a demo account; its tickets are temporary.
            <template v-if="page.props.aiSimulated">
                The AI is simulated, so it's free to play with.
            </template>
        </span>
    </div>
</template>
