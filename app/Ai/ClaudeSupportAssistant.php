<?php

namespace App\Ai;

use Anthropic\Beta\Messages\BetaOutputConfig\Effort;
use Anthropic\Beta\Messages\BetaRawContentBlockDeltaEvent;
use Anthropic\Beta\Messages\BetaRawMessageDeltaEvent;
use Anthropic\Beta\Messages\BetaTextBlock;
use Anthropic\Beta\Messages\BetaTextDelta;
use Anthropic\Client;
use App\Ai\Contracts\SupportAssistant;
use App\Ai\Data\TriageResult;
use App\Ai\Exceptions\AssistantRefusedException;
use App\Models\Ticket;
use Generator;
use JsonException;
use UnexpectedValueException;

/**
 * The real SupportAssistant, backed by the Claude API. Triage runs inside the
 * TriageTicket job; streamReply() feeds TicketReplyController's SSE stream.
 * Prompts come from TicketPromptBuilder; token budgets and effort per call
 * come from config/ai.php.
 */
class ClaudeSupportAssistant implements SupportAssistant
{
    /**
     * Server-side refusal fallback: if the model's safety classifiers decline,
     * the API retries on Anthropic's recommended fallback model for that
     * refusal category instead of returning the refusal to us.
     *
     * Kept in code rather than config: the request shape (the `fallbacks`
     * parameter) depends on this exact beta version.
     */
    private const FALLBACK_BETA = 'server-side-fallback-2026-07-01';

    /**
     * Effort must be one of the SDK's Effort values; an invalid value in
     * config fails on the first call instead of at the API.
     *
     * @param  array{max_tokens: int, effort: string}  $triageSettings
     * @param  array{max_tokens: int, effort: string}  $replySettings
     */
    public function __construct(
        private readonly Client $client,
        private readonly TicketPromptBuilder $prompts,
        private readonly string $model,
        private readonly array $triageSettings,
        private readonly array $replySettings,
    ) {}

    /**
     * The json_schema format guarantees the reply decodes into a TriageResult.
     */
    public function triage(Ticket $ticket): TriageResult
    {
        $message = $this->client->beta->messages->create(
            model: $this->model,
            maxTokens: $this->triageSettings['max_tokens'],
            system: $this->prompts->triageSystem(),
            messages: [['role' => 'user', 'content' => $this->prompts->triage($ticket)]],
            outputConfig: [
                'effort' => Effort::from($this->triageSettings['effort']),
                'format' => ['type' => 'json_schema', 'schema' => TriageResult::schema()],
            ],
            fallbacks: 'default',
            betas: [self::FALLBACK_BETA],
        );

        if ($message->stopReason === 'refusal') {
            throw AssistantRefusedException::withCategory($message->stopDetails?->category);
        }

        foreach ($message->content as $block) {
            if ($block instanceof BetaTextBlock) {
                try {
                    return TriageResult::fromArray(json_decode($block->text, true, flags: JSON_THROW_ON_ERROR));
                } catch (JsonException $e) {
                    throw new UnexpectedValueException('Triage response was not valid JSON.', previous: $e);
                }
            }
        }

        throw new UnexpectedValueException("Triage response had no text block (stop reason: {$message->stopReason}).");
    }

    public function streamReply(Ticket $ticket, ?string $guidance = null): Generator
    {
        $stream = $this->client->beta->messages->createStream(
            model: $this->model,
            maxTokens: $this->replySettings['max_tokens'],
            system: $this->prompts->replySystem(),
            messages: [['role' => 'user', 'content' => $this->prompts->reply($ticket, $guidance)]],
            outputConfig: ['effort' => Effort::from($this->replySettings['effort'])],
            fallbacks: 'default',
            betas: [self::FALLBACK_BETA],
        );

        foreach ($stream as $event) {
            if ($event instanceof BetaRawContentBlockDeltaEvent && $event->delta instanceof BetaTextDelta) {
                yield $event->delta->text;
            } elseif ($event instanceof BetaRawMessageDeltaEvent && $event->delta->stopReason === 'refusal') {
                // Declined mid-stream with no fallback able to finish: the caller
                // must discard whatever partial text it has already received.
                throw AssistantRefusedException::withCategory($event->delta->stopDetails?->category);
            }
        }
    }
}
