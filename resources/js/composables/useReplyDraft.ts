import { StreamResponseError, useJsonEventStream } from '@laravel/stream-vue';
import type { ComputedRef, Ref } from 'vue';
import { computed, ref } from 'vue';
import { reply } from '@/routes/tickets';
import type { ReplyStreamEvent, Ticket } from '@/types';

export type UseReplyDraftReturn = {
    draft: Ref<string>;
    error: Ref<string | null>;
    copied: Ref<boolean>;
    busy: ComputedRef<boolean>;
    isFetching: Readonly<Ref<boolean>>;
    isStreaming: Readonly<Ref<boolean>>;
    generate: (guidance: string) => void;
    cancel: () => void;
    copy: () => Promise<void>;
};

/**
 * Streams a drafted reply from TicketReplyController over server-sent events
 * (see ReplyStreamEvent for the event format).
 *
 * Takes a getter rather than the ticket itself because polling replaces the
 * ticket prop with a new object; the getter always sees the latest saved draft.
 */
export function useReplyDraft(ticket: () => Ticket): UseReplyDraftReturn {
    const draft = ref(ticket().draft_reply ?? '');
    const error = ref<string | null>(null);
    const copied = ref(false);

    const { send, cancel, isFetching, isStreaming } = useJsonEventStream<
        ReplyStreamEvent,
        { guidance: string }
    >(reply.url(ticket().id), {
        onEvent: (event) => {
            if (event.type === 'delta') {
                draft.value += event.text;
            } else if (event.type === 'error') {
                // The server discards partial drafts; mirror that here.
                draft.value = ticket().draft_reply ?? '';
                error.value = event.message;
            }
        },
        onError: (err) => {
            error.value =
                err instanceof StreamResponseError && err.status === 429
                    ? 'You are drafting too fast. Wait a minute and try again.'
                    : 'Could not reach the server. Please try again.';
        },
    });

    const busy = computed(() => isFetching.value || isStreaming.value);

    const generate = (guidance: string) => {
        error.value = null;
        draft.value = '';
        // Failures arrive through onError, so the promise needn't be awaited.
        void send({ guidance });
    };

    const copy = async () => {
        await navigator.clipboard.writeText(draft.value);
        copied.value = true;
        setTimeout(() => (copied.value = false), 1500);
    };

    return {
        draft,
        error,
        copied,
        busy,
        isFetching,
        isStreaming,
        generate,
        cancel,
        copy,
    };
}
