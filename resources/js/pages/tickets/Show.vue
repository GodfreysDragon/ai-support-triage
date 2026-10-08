<script setup lang="ts">
import { Head, setLayoutProps, usePoll } from '@inertiajs/vue3';
import { watch } from 'vue';
import ReplyPanel from '@/components/tickets/ReplyPanel.vue';
import TriageCard from '@/components/tickets/TriageCard.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { index, show } from '@/routes/tickets';
import type { Ticket } from '@/types';

const props = defineProps<{
    ticket: Ticket;
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Tickets', href: index() },
        { title: `#${props.ticket.id}`, href: show(props.ticket.id) },
    ],
});

// Poll for a fresh ticket until the queued triage job finishes.
const { start, stop } = usePoll(
    1500,
    { only: ['ticket'] },
    { autoStart: false },
);
watch(
    () => props.ticket.status,
    (status) => (status === 'pending' ? start() : stop()),
    { immediate: true },
);
</script>

<template>
    <Head :title="ticket.subject" />

    <div class="grid gap-6 p-4 xl:grid-cols-2">
        <div class="space-y-6">
            <Card>
                <CardHeader>
                    <CardDescription>
                        {{ ticket.customer_email ?? 'Unknown sender' }}
                    </CardDescription>
                    <CardTitle class="text-lg">{{ ticket.subject }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-sm whitespace-pre-line">{{ ticket.body }}</p>
                </CardContent>
            </Card>

            <TriageCard :ticket="ticket" />
        </div>

        <ReplyPanel :ticket="ticket" />
    </div>
</template>
