<script setup lang="ts">
import { Head, Link, useForm, usePoll } from '@inertiajs/vue3';
import { Inbox } from '@lucide/vue';
import { computed, watch } from 'vue';
import TicketController from '@/actions/App/Http/Controllers/TicketController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import TicketBadges from '@/components/TicketBadges.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
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

const form = useForm({
    customer_email: '',
    subject: '',
    body: '',
});

const samples = [
    {
        customer_email: 'ops@acme.test',
        subject: 'Dashboard returns 500 since this morning',
        body: 'Since about 9am every page in the dashboard returns a 500 error for our whole team. We have a board review at 2pm and cannot export anything. Please help ASAP.',
    },
    {
        customer_email: 'finance@globex.test',
        subject: 'Charged twice for September',
        body: 'We were billed twice for our September invoice (INV-2291). Can you refund the duplicate charge? This is the second time this has happened, which is pretty frustrating.',
    },
    {
        customer_email: 'sam@initech.test',
        subject: 'Can we schedule recurring exports?',
        body: 'Love the product! Is there a way to schedule the CSV export to run every Monday and email it to my team? If not, that would be a great feature.',
    },
];

const useSample = (sample: (typeof samples)[number]) => {
    form.clearErrors();
    Object.assign(form, sample);
};

const submit = () => form.submit(TicketController.store());

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
        <Card class="h-fit">
            <CardHeader>
                <CardTitle>New ticket</CardTitle>
                <CardDescription>
                    Paste in a customer message. It's triaged in the background
                    by a queued job.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="customer_email">Customer email</Label>
                        <Input
                            id="customer_email"
                            v-model="form.customer_email"
                            type="email"
                            placeholder="customer@example.com"
                        />
                        <InputError :message="form.errors.customer_email" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="subject">Subject</Label>
                        <Input id="subject" v-model="form.subject" required />
                        <InputError :message="form.errors.subject" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="body">Message</Label>
                        <Textarea
                            id="body"
                            v-model="form.body"
                            rows="7"
                            required
                        />
                        <InputError :message="form.errors.body" />
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <Button type="submit" :disabled="form.processing">
                            <Spinner v-if="form.processing" />
                            Submit for triage
                        </Button>
                        <span class="text-xs text-muted-foreground">
                            or try a sample:
                        </span>
                        <Button
                            v-for="(sample, i) in samples"
                            :key="i"
                            type="button"
                            size="sm"
                            variant="outline"
                            @click="useSample(sample)"
                        >
                            {{ i + 1 }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

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
