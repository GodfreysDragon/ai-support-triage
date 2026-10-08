<?php

use Anthropic\Client;
use Anthropic\RequestOptions;
use App\Ai\ClaudeSupportAssistant;
use App\Ai\TicketPromptBuilder;
use App\Enums\TicketCategory;
use App\Models\Ticket;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A PSR-18 client that records each request and answers with a canned response,
 * so the real SDK builds and sends its request without touching the network.
 */
function recordingTransport(ResponseInterface $response): ClientInterface
{
    return new class($response) implements ClientInterface
    {
        /** @var list<RequestInterface> */
        public array $requests = [];

        public function __construct(private ResponseInterface $response) {}

        public function sendRequest(RequestInterface $request): ResponseInterface
        {
            $this->requests[] = $request;

            return $this->response;
        }
    };
}

function claudeAssistant(ClientInterface $transport): ClaudeSupportAssistant
{
    $options = RequestOptions::with(transporter: $transport, streamingTransporter: $transport, maxRetries: 0);

    return new ClaudeSupportAssistant(
        new Client(apiKey: 'test-key', requestOptions: $options),
        new TicketPromptBuilder,
        'claude-opus-5-5',
        config('ai.anthropic.triage'),
        config('ai.anthropic.reply'),
    );
}

/**
 * @return array<string, mixed>
 */
function sentBody(RequestInterface $request): array
{
    return json_decode((string) $request->getBody(), true);
}

test('triage sends the schema, prompts and settings and decodes the result', function () {
    $transport = recordingTransport(new Response(200, ['Content-Type' => 'application/json'], json_encode([
        'id' => 'msg_1', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-5-5',
        'content' => [['type' => 'text', 'text' => json_encode([
            'category' => 'billing', 'priority' => 'medium', 'sentiment' => 'negative',
            'summary' => 'Customer was charged twice.', 'tags' => ['invoice'],
        ])]],
        'stop_reason' => 'end_turn', 'stop_sequence' => null,
        'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
    ])));
    $ticket = Ticket::factory()->make(['subject' => 'Charged twice', 'customer_email' => 'a@b.test']);

    $result = claudeAssistant($transport)->triage($ticket);

    $request = $transport->requests[0];
    $body = sentBody($request);
    expect($result->category)->toBe(TicketCategory::Billing)
        ->and($request->getHeaderLine('anthropic-beta'))->toContain('server-side-fallback-2026-07-01')
        ->and($body['model'])->toBe('claude-opus-5-5')
        ->and($body['max_tokens'])->toBe(4096)
        ->and($body['output_config']['effort'])->toBe('low')
        ->and($body['output_config']['format']['type'])->toBe('json_schema')
        ->and($body['fallbacks'])->toBe('default')
        ->and($body['system'])->toStartWith('You triage inbound customer support tickets')
        ->and($body['messages'][0]['content'])->toStartWith("<ticket>\nFrom: a@b.test\nSubject: Charged twice");
});

test('streamReply sends the reply prompt and yields text deltas', function () {
    $events = [
        ['message_start', ['type' => 'message_start', 'message' => [
            'id' => 'msg_1', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-5-5',
            'content' => [], 'stop_reason' => null, 'stop_sequence' => null,
            'usage' => ['input_tokens' => 10, 'output_tokens' => 0],
        ]]],
        ['content_block_start', ['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'text', 'text' => '']]],
        ['content_block_delta', ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'text_delta', 'text' => 'Hi ']]],
        ['content_block_delta', ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'text_delta', 'text' => 'there']]],
        ['content_block_stop', ['type' => 'content_block_stop', 'index' => 0]],
        ['message_delta', ['type' => 'message_delta', 'delta' => ['stop_reason' => 'end_turn', 'stop_sequence' => null], 'usage' => ['output_tokens' => 2]]],
        ['message_stop', ['type' => 'message_stop']],
    ];
    $sse = collect($events)->map(fn ($e) => "event: {$e[0]}\ndata: ".json_encode($e[1])."\n\n")->implode('');
    $transport = recordingTransport(new Response(200, ['Content-Type' => 'text/event-stream'], $sse));
    $ticket = Ticket::factory()->triaged()->make();

    $text = implode('', iterator_to_array(claudeAssistant($transport)->streamReply($ticket, 'Keep it short'), false));

    $body = sentBody($transport->requests[0]);
    expect($text)->toBe('Hi there')
        ->and($body['max_tokens'])->toBe(16000)
        ->and($body['output_config']['effort'])->toBe('medium')
        ->and($body['system'])->toStartWith('You are a senior customer support agent')
        ->and($body['messages'][0]['content'])->toContain('<triage>')
        ->and($body['messages'][0]['content'])->toContain('<agent_guidance>Keep it short</agent_guidance>');
});
