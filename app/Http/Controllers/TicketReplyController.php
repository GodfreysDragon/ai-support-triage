<?php

namespace App\Http\Controllers;

use App\Ai\Contracts\SupportAssistant;
use App\Ai\Exceptions\AssistantRefusedException;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class TicketReplyController extends Controller
{
    /**
     * Stream a drafted reply to the browser as server-sent events.
     *
     * Each event is JSON: {type: "delta", text} while generating, then
     * {type: "done"} or {type: "error", message}.
     */
    public function __invoke(Request $request, Ticket $ticket, SupportAssistant $assistant): StreamedResponse
    {
        Gate::authorize('update', $ticket);

        $guidance = $request->validate([
            'guidance' => ['nullable', 'string', 'max:1000'],
        ])['guidance'] ?? null;

        return response()->eventStream(function () use ($assistant, $ticket, $guidance) {
            $reply = '';

            try {
                foreach ($assistant->streamReply($ticket, $guidance) as $text) {
                    $reply .= $text;

                    yield ['type' => 'delta', 'text' => $text];
                }
            } catch (AssistantRefusedException $e) {
                yield ['type' => 'error', 'message' => $e->getMessage()];

                return;
            } catch (Throwable $e) {
                report($e);

                yield ['type' => 'error', 'message' => 'The AI service is unavailable right now. Please try again.'];

                return;
            }

            // Only a completed stream is persisted; partial output is discarded.
            $ticket->update(['draft_reply' => $reply]);

            yield ['type' => 'done'];
        });
    }
}
