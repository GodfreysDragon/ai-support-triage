<script setup lang="ts">
import { Head, router, setLayoutProps, usePoll } from '@inertiajs/vue3';
import { StreamResponseError, useJsonEventStream } from '@laravel/stream-vue';
import { Check, Copy, RotateCw, Sparkles, Square } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import TicketController from '@/actions/App/Http/Controllers/TicketController';
import AlertError from '@/components/AlertError.vue';
import TicketBadges from '@/components/TicketBadges.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { Textarea } from '@/components/ui/textarea';
import { index, reply, show } from '@/routes/tickets';
import type { ReplyStreamEvent, Ticket } from '@/types';

const props = defineProps<{
    ticket: Ticket;
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Tickets', href: index() },
        { title: `#${props.ticket.id}`, href: show(props.ticket.id) },
    ],
});

// --- Triage status: poll until the queued job finishes -------------------

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

const retriage = () =>
    router.visit(TicketController.retriage(props.ticket.id), {
        preserveScroll: true,
    });

// --- Reply drafting: stream tokens from the SSE endpoint -----------------

const guidance = ref('');
const draft = ref(props.ticket.draft_reply ?? '');
const streamError = ref<string | null>(null);
const copied = ref(false);

const { send, cancel, isFetching, isStreaming } = useJsonEventStream<
    ReplyStreamEvent,
    { guidance: string }
>(reply.url(props.ticket.id), {
    onEvent: (event) => {
        if (event.type === 'delta') {
            draft.value += event.text;
        } else if (event.type === 'error') {
            // The server discards partial drafts; mirror that here.
            draft.value = props.ticket.draft_reply ?? '';
            streamError.value = event.message;
        }
    },
    onError: (error) => {
        streamError.value =
            error instanceof StreamResponseError && error.status === 429
                ? 'You are drafting too fast. Wait a minute and try again.'
                : 'Could not reach the server. Please try again.';
    },
});

const busy = computed(() => isFetching.value || isStreaming.value);

const generate = () => {
    streamError.value = null;
    draft.value = '';
    send({ guidance: guidance.value });
};

const copy = async () => {
    await navigator.clipboard.writeText(draft.value);
    copied.value = true;
    setTimeout(() => (copied.value = false), 1500);
};
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

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Sparkles class="size-4" /> AI triage
                    </CardTitle>
                    <CardDescription>
                        Classified by a queued job using structured outputs.
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
                        <div
                            v-if="ticket.tags.length"
                            class="flex flex-wrap gap-1.5"
                        >
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
        </div>

        <Card class="h-fit">
            <CardHeader>
                <CardTitle>Draft a reply</CardTitle>
                <CardDescription>
                    Streamed token by token over server-sent events.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid gap-2">
                    <Label for="guidance">Guidance (optional)</Label>
                    <Textarea
                        id="guidance"
                        v-model="guidance"
                        rows="2"
                        maxlength="1000"
                        placeholder="e.g. Offer a 20% credit and keep it short"
                        :disabled="busy"
                    />
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button v-if="!busy" @click="generate">
                        <Sparkles />
                        {{ draft ? 'Regenerate' : 'Generate reply' }}
                    </Button>
                    <Button v-else variant="outline" @click="cancel">
                        <Square /> Stop
                    </Button>
                    <Button v-if="draft && !busy" variant="ghost" @click="copy">
                        <component :is="copied ? Check : Copy" />
                        {{ copied ? 'Copied' : 'Copy' }}
                    </Button>
                </div>

                <AlertError
                    v-if="streamError"
                    :errors="[streamError]"
                    title="Couldn't draft a reply"
                />

                <div
                    class="min-h-48 rounded-md border bg-muted/30 p-4 text-sm whitespace-pre-wrap"
                    aria-live="polite"
                >
                    <span v-if="draft">{{ draft }}</span>
                    <span v-else-if="isFetching" class="text-muted-foreground">
                        Thinking…
                    </span>
                    <span v-else class="text-muted-foreground">
                        The drafted reply will appear here.
                    </span>
                    <span
                        v-if="isStreaming"
                        class="ml-0.5 inline-block h-4 w-1.5 animate-pulse bg-foreground align-text-bottom"
                    />
                </div>
            </CardContent>
        </Card>
    </div>
</template>
