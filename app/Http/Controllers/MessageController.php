<?php

namespace App\Http\Controllers;

use App\Jobs\SendWhatsAppMessage;
use App\Models\WhatsAppMessage;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MessageController extends Controller
{
    public function index(Request $request): View
    {
        $messages = WhatsAppMessage::with(['customer', 'template'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->when($request->filled('type'), fn ($query) => $query->where('message_type', $request->type))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('messages.index', compact('messages'));
    }

    public function retry(Request $request, string $message, AuditService $audit): RedirectResponse
    {
        $message = WhatsAppMessage::where('public_id', $message)->firstOrFail();
        abort_unless(in_array($message->status, ['failed', 'cancelled', 'queued'], true), 422);
        if ($message->attempts >= config('whatsapp.max_attempts')) {
            return back()->withErrors(['message' => 'Este mensaje alcanzó el máximo de intentos configurado.']);
        }
        $message->update(['status' => 'queued', 'error_code' => null, 'error_message' => null, 'queued_at' => now()]);
        $message->campaignRecipient?->update(['status' => 'queued', 'processed_at' => null]);
        SendWhatsAppMessage::dispatch($message->id)->onQueue('messages');
        $audit->record('whatsapp_message.retried', $message, businessId: $message->business_id, userId: $request->user()->id, request: $request);

        return back()->with('success', 'Mensaje reenviado a la cola.');
    }

    public function simulate(Request $request, string $message): RedirectResponse
    {
        abort_unless(config('whatsapp.provider') === 'fake' || $request->user()->business->whatsappAccount?->provider === 'fake', 404);
        $data = $request->validate(['status' => ['required', Rule::in(['delivered', 'read', 'failed'])]]);
        $message = WhatsAppMessage::where('public_id', $message)->firstOrFail();
        $updates = ['status' => $data['status']];
        $updates[$data['status'].'_at'] = now();
        if ($data['status'] === 'failed') {
            $updates['error_code'] = 'FAKE_FAILURE';
            $updates['error_message'] = 'Fallo marcado manualmente en el entorno local.';
        }
        $message->update($updates);
        $message->campaignRecipient?->update([
            'status' => $data['status'],
            'processed_at' => now(),
        ]);

        return back()->with('success', 'Estado local actualizado.');
    }
}
