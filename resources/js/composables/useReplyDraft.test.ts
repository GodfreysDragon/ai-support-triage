import {
    afterEach,
    beforeEach,
    describe,
    expect,
    test,
    vi,
} from 'vite-plus/test';
import type { ReplyStreamEvent, Ticket } from '@/types';
import { useReplyDraft } from './useReplyDraft';

type StreamOptions = {
    onEvent: (event: ReplyStreamEvent) => void;
    onError: (error: unknown) => void;
};

// The real useJsonEventStream needs a server; this stand-in captures the
// callbacks so each test can play events into the composable directly.
const stream = vi.hoisted(() => ({
    options: null as StreamOptions | null,
    send: vi.fn(),
    cancel: vi.fn(),
}));

vi.mock('@laravel/stream-vue', async () => {
    const { ref } = await import('vue');

    class StreamResponseError extends Error {
        constructor(public status: number) {
            super(`Request failed with status ${status}.`);
        }
    }

    return {
        StreamResponseError,
        useJsonEventStream: (_url: string, options: StreamOptions) => {
            stream.options = options;

            return {
                send: stream.send,
                cancel: stream.cancel,
                isFetching: ref(false),
                isStreaming: ref(false),
            };
        },
    };
});

const clipboard = vi.hoisted(() => ({ copy: vi.fn() }));
vi.mock('@/lib/clipboard', () => ({ copyToClipboard: clipboard.copy }));

function makeTicket(draft: string | null = null): Ticket {
    return {
        id: 7,
        customer_email: 'jane@example.com',
        subject: 'Charged twice',
        body: 'Please refund the duplicate charge.',
        status: 'triaged',
        category: 'billing',
        priority: 'medium',
        sentiment: 'negative',
        summary: 'Customer was charged twice.',
        tags: [],
        error: null,
        draft_reply: draft,
        triaged_at: '2026-10-08T12:00:00+00:00',
        created_at: '2026-10-08T12:00:00+00:00',
    };
}

function play(...events: ReplyStreamEvent[]) {
    events.forEach((event) => stream.options!.onEvent(event));
}

beforeEach(() => {
    stream.send.mockClear();
    stream.cancel.mockClear();
    clipboard.copy.mockReset();
});

afterEach(() => {
    vi.useRealTimers();
});

describe('useReplyDraft', () => {
    test('starts with the ticket’s saved draft', () => {
        expect(useReplyDraft(() => makeTicket('Saved reply')).draft.value).toBe(
            'Saved reply',
        );
    });

    test('generate clears the draft and sends the guidance', () => {
        const reply = useReplyDraft(() => makeTicket('Saved reply'));

        reply.generate('Keep it short');

        expect(reply.draft.value).toBe('');
        expect(stream.send).toHaveBeenCalledWith({ guidance: 'Keep it short' });
    });

    test('text deltas are appended as they arrive', () => {
        const reply = useReplyDraft(() => makeTicket());

        reply.generate('');
        play(
            { type: 'delta', text: 'Hi ' },
            { type: 'delta', text: 'there' },
            { type: 'done' },
        );

        expect(reply.draft.value).toBe('Hi there');
    });

    test('stop keeps the partial text, marks it unsaved and offers the saved draft', () => {
        const reply = useReplyDraft(() => makeTicket('Saved reply'));

        reply.generate('');
        play({ type: 'delta', text: 'Partial' });
        reply.stop();

        expect(stream.cancel).toHaveBeenCalled();
        expect(reply.draft.value).toBe('Partial');
        expect(reply.stopped.value).toBe(true);
        expect(reply.canRestore.value).toBe(true);

        reply.restore();

        expect(reply.draft.value).toBe('Saved reply');
        expect(reply.stopped.value).toBe(false);
        expect(reply.canRestore.value).toBe(false);
    });

    test('restore brings back the latest finished draft, not the one the page loaded with', () => {
        const reply = useReplyDraft(() => makeTicket('Loaded with page'));

        reply.generate('');
        play({ type: 'delta', text: 'Newer finished reply' }, { type: 'done' });
        reply.generate('');
        play({ type: 'delta', text: 'Partial' });
        reply.stop();
        reply.restore();

        expect(reply.draft.value).toBe('Newer finished reply');
    });

    test('there is nothing to restore when no draft has ever finished', () => {
        const reply = useReplyDraft(() => makeTicket());

        reply.generate('');
        play({ type: 'delta', text: 'Partial' });
        reply.stop();

        expect(reply.stopped.value).toBe(true);
        expect(reply.canRestore.value).toBe(false);
    });

    test('an error event shows the message and puts back the latest finished draft', () => {
        const reply = useReplyDraft(() => makeTicket('Loaded with page'));

        reply.generate('');
        play({ type: 'delta', text: 'Finished reply' }, { type: 'done' });
        reply.generate('');
        play(
            { type: 'delta', text: 'Partial' },
            { type: 'error', message: 'Declined for safety.' },
        );

        expect(reply.draft.value).toBe('Finished reply');
        expect(reply.error.value).toBe('Declined for safety.');
    });

    test('a 429 response explains the rate limit', async () => {
        const { StreamResponseError } = await import('@laravel/stream-vue');
        const reply = useReplyDraft(() => makeTicket('Saved reply'));

        reply.generate('');
        stream.options!.onError(
            new (StreamResponseError as never as new (status: number) => Error)(
                429,
            ),
        );

        expect(reply.error.value).toBe(
            'You are drafting too fast. Wait a minute and try again.',
        );
        expect(reply.draft.value).toBe('Saved reply');
    });

    test('other request failures show a generic message', () => {
        const reply = useReplyDraft(() => makeTicket());

        reply.generate('');
        stream.options!.onError(new TypeError('Failed to fetch'));

        expect(reply.error.value).toBe(
            'Could not reach the server. Please try again.',
        );
    });

    test('generating again clears the stopped state and the error', () => {
        const reply = useReplyDraft(() => makeTicket('Saved reply'));

        reply.generate('');
        play({ type: 'error', message: 'Oops' });
        reply.stop();
        reply.generate('');

        expect(reply.error.value).toBeNull();
        expect(reply.stopped.value).toBe(false);
    });

    test('copy reports success, then resets', async () => {
        vi.useFakeTimers();
        clipboard.copy.mockResolvedValue(undefined);
        const reply = useReplyDraft(() => makeTicket('Saved reply'));

        await reply.copy();

        expect(clipboard.copy).toHaveBeenCalledWith('Saved reply');
        expect(reply.copyState.value).toBe('copied');

        vi.advanceTimersByTime(2000);

        expect(reply.copyState.value).toBe('idle');
    });

    test('copy reports failure instead of failing silently', async () => {
        clipboard.copy.mockRejectedValue(new Error('denied'));
        const reply = useReplyDraft(() => makeTicket('Saved reply'));

        await reply.copy();

        expect(reply.copyState.value).toBe('failed');
    });
});
