<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { RotateCw, Sparkles } from '@lucide/vue';
import TicketController from '@/actions/App/Http/Controllers/TicketController';
import AlertError from '@/components/AlertError.vue';
import TicketBadges from '@/components/tickets/TicketBadges.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import type { Ticket } from '@/types';

const props = defineProps<{
    ticket: Ticket;
}>();

const retriage = () =>
    router.visit(TicketController.retriage(props.ticket.id), {
        preserveScroll: true,
    });
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Sparkles class="size-4" /> AI triage
            </CardTitle>
            <CardDescription>
                Category, urgency and mood, read from the message.
            </CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
            <TicketBadges :ticket="ticket" />

            <div v-if="ticket.status === 'pending'" class="space-y-2">
                <Skeleton class="h-4 w-3/4" />
                <Skeleton class="h-4 w-1/2" />
            </div>

            <template v-else-if="ticket.status === 'failed'">
                <AlertError
                    :errors="[ticket.error ?? 'Triage failed.']"
                    title="Triage failed"
                />
                <Button variant="outline" size="sm" @click="retriage">
                    <RotateCw /> Retry triage
                </Button>
            </template>

            <template v-else>
                <p class="text-sm">{{ ticket.summary }}</p>
                <div v-if="ticket.tags.length" class="flex flex-wrap gap-1.5">
                    <Badge
                        v-for="tag in ticket.tags"
                        :key="tag"
                        variant="outline"
                        class="font-mono"
                    >
                        #{{ tag }}
                    </Badge>
                </div>
            </template>
        </CardContent>
    </Card>
</template>
