<?php

namespace App\Services\Messaging;

use App\Models\MessageDispatch;
use App\Models\Society;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * The single door every automated message leaves by.
 *
 * Composing, recording and sending are kept together so that nothing can be
 * sent without a record of it, and so a duplicate send is stopped by the
 * database rather than by each caller remembering to check. A caller supplies
 * a template key, a recipient and the values; everything else is handled here.
 */
class Messenger
{
    public function __construct(private TemplateRenderer $renderer) {}

    /**
     * Sends one message, unless an identical one has already gone out.
     *
     * The dedupe key is the caller's statement of what "identical" means --
     * usually the template, the recipient and the thing it is about on a given
     * day. A clash is not an error: it means the work was already done.
     *
     * @param  array<string, mixed>  $data
     * @return MessageDispatch|null Null when the message was a duplicate.
     */
    public function send(
        Society $society,
        string $templateKey,
        User $recipient,
        array $data,
        string $channel = 'email',
        ?Model $related = null,
        ?string $dedupeKey = null,
    ): ?MessageDispatch {
        $rendered = $this->renderer->render($society, $templateKey, $data + [
            'resident_name' => $recipient->name,
        ], $channel);

        try {
            $dispatch = MessageDispatch::create([
                'society_id' => $society->id,
                'template_key' => $templateKey,
                'channel' => $channel,
                'user_id' => $recipient->id,
                'recipient' => $channel === 'email' ? $recipient->email : $recipient->phone,
                'subject' => $rendered['subject'] ?: null,
                'body' => $rendered['body'],
                'related_type' => $related?->getMorphClass(),
                'related_id' => $related?->getKey(),
                'dedupe_key' => $dedupeKey,
                'status' => 'queued',
            ]);
        } catch (QueryException $e) {
            // The unique index on (society_id, dedupe_key) did its job.
            if ($this->isDuplicate($e)) {
                return null;
            }

            throw $e;
        }

        $this->deliver($dispatch, $recipient, $rendered, $channel);

        return $dispatch;
    }

    /**
     * Hands the composed message to its channel.
     *
     * Only email is wired to a real transport. SMS and WhatsApp are recorded
     * and marked queued rather than pretended to be sent: a society that has
     * not connected a provider should see an honest "queued", not a receipt
     * for a message nobody got.
     *
     * @param  array{subject: string, body: string}  $rendered
     */
    private function deliver(MessageDispatch $dispatch, User $recipient, array $rendered, string $channel): void
    {
        if ($channel !== 'email') {
            return;
        }

        if (blank($recipient->email)) {
            $dispatch->forceFill(['status' => 'failed', 'error' => 'No email address on file.'])->save();

            return;
        }

        try {
            Mail::raw($rendered['body'], function ($message) use ($recipient, $rendered) {
                $message->to($recipient->email)->subject($rendered['subject'] ?: 'A message from your society');
            });

            $dispatch->forceFill(['status' => 'sent', 'sent_at' => now()])->save();
        } catch (\Throwable $e) {
            Log::warning('Message delivery failed', [
                'dispatch' => $dispatch->id,
                'error' => $e->getMessage(),
            ]);

            $dispatch->forceFill(['status' => 'failed', 'error' => $e->getMessage()])->save();
        }
    }

    private function isDuplicate(QueryException $e): bool
    {
        return in_array($e->getCode(), ['23000', '23505'], true);
    }
}
