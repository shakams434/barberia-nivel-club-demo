<?php

namespace App\Http\Controllers;

use App\Models\InboundMessage;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppConversation;
use App\Services\AuditService;
use App\Services\WhatsAppMessageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WhatsAppConversationController extends Controller
{
    public function index(Request $request): View
    {
        return $this->render($request);
    }

    public function show(Request $request, string $conversation): View
    {
        $selected = WhatsAppConversation::where('public_id', $conversation)->with('customer')->firstOrFail();
        $selected->update(['unread_count' => 0]);
        $selected->inboundMessages()->whereNull('read_at')->update(['read_at' => now()]);

        return $this->render($request, $selected->fresh('customer'));
    }

    public function reply(Request $request, string $conversation, WhatsAppMessageService $messages, AuditService $audit): RedirectResponse
    {
        $conversation = WhatsAppConversation::where('public_id', $conversation)->firstOrFail();
        $data = $request->validate(['message' => ['required', 'string', 'max:4096']]);
        $account = WhatsAppAccount::first();

        if ($account?->provider === 'meta' && ! $conversation->sessionIsOpen()) {
            return back()->withErrors(['message' => 'La ventana de 24 horas terminó. Para retomar la conversación debes usar una plantilla aprobada.']);
        }

        $message = $messages->queueConversationText($conversation, $data['message'], 'manual-reply:'.$conversation->id.':'.Str::uuid());
        $messages->attemptNow($message->id, true);
        $conversation->inboundMessages()->whereNull('replied_at')->update(['replied_at' => now(), 'read_at' => now()]);
        $audit->record('whatsapp.conversation_replied', $conversation, after: ['message_id' => $message->public_id], request: $request);

        return redirect()->route('whatsapp.conversations.show', $conversation)->with('success', 'Respuesta enviada a la conversación.');
    }

    private function render(Request $request, ?WhatsAppConversation $selected = null): View
    {
        $query = trim((string) $request->query('q'));
        $conversations = WhatsAppConversation::with('customer')
            ->when($query !== '', function ($builder) use ($query): void {
                $digits = preg_replace('/\D+/', '', $query);
                $builder->where(function ($builder) use ($query, $digits): void {
                    $builder->where('contact_name', 'like', '%'.$query.'%')
                        ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', '%'.$query.'%'));
                    if ($digits !== '') {
                        $builder->orWhere('phone_e164', 'like', '%'.substr($digits, -4));
                    }
                });
            })
            ->orderByDesc('last_message_at')
            ->paginate(30)
            ->withQueryString();

        foreach ($conversations as $conversation) {
            $inbound = $conversation->inboundMessages()->latest()->first();
            $outbound = $conversation->outboundMessages()->latest()->first();
            $latest = collect([$inbound, $outbound])->filter()->sortByDesc('created_at')->first();
            $conversation->setAttribute('last_preview', $latest?->message_text ?? $latest?->body_preview ?? 'Conversación iniciada');
            $conversation->setAttribute('last_direction', $latest instanceof InboundMessage ? 'inbound' : 'outbound');
        }

        $timeline = collect();
        if ($selected) {
            $timeline = $selected->inboundMessages()->get()->map(fn ($message) => [
                'direction' => 'inbound', 'body' => $message->message_text ?: 'Mensaje no textual',
                'status' => $message->status, 'at' => $message->created_at, 'id' => 'in-'.$message->id,
            ])->merge($selected->outboundMessages()->get()->map(fn ($message) => [
                'direction' => 'outbound', 'body' => $message->body_preview ?: 'Mensaje enviado',
                'status' => $message->status, 'at' => $message->created_at, 'id' => 'out-'.$message->id,
            ]))->sortBy('at')->values();
        }

        return view('whatsapp.conversations', [
            'conversations' => $conversations,
            'selectedConversation' => $selected,
            'timeline' => $timeline,
            'account' => WhatsAppAccount::first(),
            'query' => $query,
        ]);
    }
}
