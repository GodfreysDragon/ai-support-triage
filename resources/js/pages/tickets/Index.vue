<script setup lang="ts">
import { Head, Link, usePoll } from '@inertiajs/vue3';
import { Inbox } from '@lucide/vue';
import { computed, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import NewTicketForm from '@/components/tickets/NewTicketForm.vue';
import TicketBadges from '@/components/tickets/TicketBadges.vue';
import { Button } from '@/components/ui/button';
import { index, show } from '@/routes/tickets';
import type { Paginated, Ticket } from '@/types';

const props = defineProps<{
    tickets: Paginated<Ticket>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Tickets', href: index() }],
    },
});

// Keep the list fresh while any ticket is still waiting for triage.
const hasPending = computed(() =>
    props.tickets.data.some((ticket) => ticket.status === 'pending'),
);
const { start, stop } = usePoll(
    2000,
    { only: ['tickets'] },
    { autoStart: false },
);
watch(hasPending, (pending) => (pending ? start() : stop()), {
    immediate: true,
});

const formatDate = (iso: string) =>
    new Date(iso).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
</script>

<template>
    <Head title="Tickets" />

    <div class="grid gap-6 p-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
        <NewTicketForm />

        <section>
            <Heading
                title="Queue"
                :description="`${tickets.total} ticket${tickets.total === 1 ? '' : 's'}`"
            />

            <div
                v-if="tickets.data.length === 0"
                class="flex flex-col items-center gap-2 rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground"
            >
                <Inbox class="size-6" />
                No tickets yet. Submit one to see triage in action.
            </div>

            <ul v-else class="divide-y rounded-xl border">
                <li v-for="ticket in tickets.data" :key="ticket.id">
                    <Link
                        :href="show(ticket.id)"
                        class="flex flex-col gap-1.5 p-4 transition-colors hover:bg-accent/50"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <span class="font-medium">{{
                                ticket.subject
                            }}</span>
                            <span
                                class="shrink-0 text-xs text-muted-foreground"
                            >
                                {{ formatDate(ticket.created_at) }}
                            </span>
                        </div>
                        <p class="line-clamp-1 text-sm text-muted-foreground">
                            {{ ticket.summary ?? ticket.body }}
                        </p>
                        <TicketBadges :ticket="ticket" />
                    </Link>
                </li>
            </ul>

            <div
                v-if="tickets.last_page > 1"
                class="mt-4 flex items-center justify-between text-sm"
            >
                <Button variant="outline" size="sm" as-child>
                    <Link
                        v-if="tickets.prev_page_url"
                        :href="tickets.prev_page_url"
                    >
                        Previous
                    </Link>
                    <span v-else class="pointer-events-none opacity-50">
                        Previous
                    </span>
                </Button>
                <span class="text-muted-foreground">
                    Page {{ tickets.current_page }} of {{ tickets.last_page }}
                </span>
                <Button variant="outline" size="sm" as-child>
                    <Link
                        v-if="tickets.next_page_url"
                        :href="tickets.next_page_url"
                    >
                        Next
                    </Link>
                    <span v-else class="pointer-events-none opacity-50">
                        Next
                    </span>
                </Button>
            </div>
        </section>
    </div>
</template>
