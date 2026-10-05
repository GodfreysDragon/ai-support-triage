<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { AlertTriangle, Clock, Inbox, Siren } from '@lucide/vue';
import { computed } from 'vue';
import TicketBadges from '@/components/TicketBadges.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';
import { index, show } from '@/routes/tickets';
import type { Ticket } from '@/types';

const props = defineProps<{
    stats: { total: number; pending: number; urgent: number; failed: number };
    byCategory: Record<string, number>;
    recent: Ticket[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const tiles = computed(() => [
    { label: 'Total tickets', value: props.stats.total, icon: Inbox },
    { label: 'Awaiting triage', value: props.stats.pending, icon: Clock },
    { label: 'Urgent', value: props.stats.urgent, icon: Siren },
    { label: 'Failed triage', value: props.stats.failed, icon: AlertTriangle },
]);

const categoryMax = computed(() =>
    Math.max(1, ...Object.values(props.byCategory)),
);
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card v-for="tile in tiles" :key="tile.label" class="gap-2">
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardDescription>{{ tile.label }}</CardDescription>
                    <component
                        :is="tile.icon"
                        class="size-4 text-muted-foreground"
                    />
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-semibold tabular-nums">
                        {{ tile.value }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
            <Card>
                <CardHeader>
                    <CardTitle>By category</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <p
                        v-if="Object.keys(byCategory).length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        Nothing triaged yet.
                    </p>
                    <div
                        v-for="(count, category) in byCategory"
                        :key="category"
                        class="space-y-1"
                    >
                        <div class="flex justify-between text-sm">
                            <span class="capitalize">
                                {{ String(category).replace('_', ' ') }}
                            </span>
                            <span class="text-muted-foreground tabular-nums">
                                {{ count }}
                            </span>
                        </div>
                        <div class="h-2 rounded-full bg-muted">
                            <div
                                class="h-2 rounded-full bg-primary"
                                :style="{
                                    width: `${(count / categoryMax) * 100}%`,
                                }"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>Recent tickets</CardTitle>
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="index()">Open queue</Link>
                    </Button>
                </CardHeader>
                <CardContent>
                    <p
                        v-if="recent.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        No tickets yet.
                        <Link :href="index()" class="underline">
                            Create your first one.
                        </Link>
                    </p>
                    <ul v-else class="divide-y">
                        <li v-for="ticket in recent" :key="ticket.id">
                            <Link
                                :href="show(ticket.id)"
                                class="flex flex-wrap items-center justify-between gap-2 py-3 hover:underline"
                            >
                                <span class="text-sm font-medium">
                                    {{ ticket.subject }}
                                </span>
                                <TicketBadges :ticket="ticket" />
                            </Link>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
