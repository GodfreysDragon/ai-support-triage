export type TicketStatus = 'pending' | 'triaged' | 'failed';
export type TicketCategory =
    | 'billing'
    | 'bug'
    | 'feature_request'
    | 'account'
    | 'how_to'
    | 'other';
export type TicketPriority = 'low' | 'medium' | 'high' | 'urgent';
export type Sentiment = 'positive' | 'neutral' | 'negative' | 'angry';

export type Ticket = {
    id: number;
    customer_email: string | null;
    subject: string;
    body: string;
    status: TicketStatus;
    category: TicketCategory | null;
    priority: TicketPriority | null;
    sentiment: Sentiment | null;
    summary: string | null;
    tags: string[];
    error: string | null;
    draft_reply: string | null;
    triaged_at: string | null;
    created_at: string;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
};

/** Events emitted by the reply SSE endpoint (App\Http\Controllers\TicketReplyController). */
export type ReplyStreamEvent =
    | { type: 'delta'; text: string }
    | { type: 'done' }
    | { type: 'error'; message: string };
