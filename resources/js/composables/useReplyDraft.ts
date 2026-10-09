import { StreamResponseError, useJsonEventStream } from '@laravel/stream-vue';
import type { ComputedRef, Ref } from 'vue';
import { computed, ref } from 'vue';
import { copyToClipboard } from '@/lib/clipboard';
import { reply } from '@/routes/tickets';
import type { ReplyStreamEvent, Ticket } from '@/types';

export type CopyState = 'idle' | 'copied' | 'failed';

export type UseReplyDraftReturn = {
    draft: Ref<string>;
    error: Ref<string | null>;
    copyState: Ref<CopyState>;
    stopped: Ref<boolean>;
    canRestore: ComputedRef<boolean>;
    busy: ComputedRef<boolean>;
    isFetching: Readonly<Ref<boolean>>;
    isStreaming: Readonly<Ref<boolean>>;
    generate: (guidance: string) => void;
    stop: () => void;
    restore: () => void;
    copy: () => Promise<void>;
};

/**
 * Streams a drafted reply from TicketReplyController over server-sent events
 * (see ReplyStreamEvent for the event format).
 *
 * The server saves a draft only when its stream finishes, so `saved` tracks
 * the last finished draft: it starts as the ticket's stored draft and is
 * replaced on every "done" event. A stopped or failed stream never touches it,
 * and it's what Restore (and an error) puts back.
 */
export function useReplyDraft(ticket: () => Ticket): UseReplyDraftReturn {
    const saved = ref(ticket().draft_reply ?? '');
    const draft = ref(saved.value);
    const error = ref<string | null>(null);
    const copyState = ref<CopyState>('idle');
    const stopped = ref(false);

    const { send, cancel, isFetching, isStreaming } = useJsonEventStream<
        ReplyStreamEvent,
        { guidance: string }
    >(reply.url(ticket().id), {
        onEvent: (event) => {
            if (event.type === 'delta') {
                draft.value += event.text;
            } else if (event.type === 'done') {
                saved.value = draft.value;
            } else if (event.type === 'error') {
                // The server discards partial drafts; mirror that here.
                draft.value = saved.value;
                error.value = event.message;
            }
        },
        onError: (err) => {
            draft.value = saved.value;
            error.value =
                err instanceof StreamResponseError && err.status === 429
                    ? 'You are drafting too fast. Wait a minute and try again.'
                    : 'Could not reach the server. Please try again.';
        },
    });

    const busy = computed(() => isFetching.value || isStreaming.value);

    // Offer Restore only when there's a finished draft the partial one replaced.
    const canRestore = computed(
        () =>
            stopped.value && saved.value !== '' && draft.value !== saved.value,
    );

    const generate = (guidance: string) => {
        error.value = null;
        stopped.value = false;
        draft.value = '';
        // Failures arrive through onError, so the promise needn't be awaited.
        void send({ guidance });
    };

    // Keeps the partial text on screen (it may be worth copying) but marks it unsaved.
    const stop = () => {
        cancel();
        stopped.value = true;
    };

    const restore = () => {
        draft.value = saved.value;
        stopped.value = false;
    };

    // The button shows "Copied" or "Copy failed" briefly, then resets.
    const copy = async () => {
        try {
            await copyToClipboard(draft.value);
            copyState.value = 'copied';
        } catch {
            copyState.value = 'failed';
        }

        setTimeout(() => (copyState.value = 'idle'), 2000);
    };

    return {
        draft,
        error,
        copyState,
        stopped,
        canRestore,
        busy,
        isFetching,
        isStreaming,
        generate,
        stop,
        restore,
        copy,
    };
}
