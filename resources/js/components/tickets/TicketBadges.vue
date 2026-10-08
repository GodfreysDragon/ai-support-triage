<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import type { Ticket, TicketPriority } from '@/types';

defineProps<{
    ticket: Pick<Ticket, 'status' | 'category' | 'priority' | 'sentiment'>;
}>();

const priorityClass: Record<TicketPriority, string> = {
    urgent: 'border-transparent bg-red-600 text-white',
    high: 'border-transparent bg-orange-500 text-white',
    medium: 'border-transparent bg-amber-200 text-amber-950 dark:bg-amber-400/80',
    low: 'border-transparent bg-secondary text-secondary-foreground',
};

const label = (value: string) => value.replace('_', ' ');
</script>

<template>
    <div class="flex flex-wrap items-center gap-1.5">
        <Badge v-if="ticket.status === 'pending'" variant="outline">
            <Spinner class="size-3" /> Triaging
        </Badge>
        <Badge v-else-if="ticket.status === 'failed'" variant="destructive">
            Triage failed
        </Badge>
        <template v-else>
            <Badge
                v-if="ticket.priority"
                :class="cn('capitalize', priorityClass[ticket.priority])"
            >
                {{ ticket.priority }}
            </Badge>
            <Badge
                v-if="ticket.category"
                variant="secondary"
                class="capitalize"
            >
                {{ label(ticket.category) }}
            </Badge>
            <Badge v-if="ticket.sentiment" variant="outline" class="capitalize">
                {{ ticket.sentiment }}
            </Badge>
        </template>
    </div>
</template>
