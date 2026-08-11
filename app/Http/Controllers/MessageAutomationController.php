<?php

namespace App\Http\Controllers;

use App\Models\MessageAutomation;
use App\Models\WhatsAppTemplate;
use App\Services\AuditService;
use App\Services\MessageAutomationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MessageAutomationController extends Controller
{
    public function update(Request $request, MessageAutomationService $service, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'event_key' => ['required', Rule::in(array_keys($service->definitions()))],
            'whatsapp_template_id' => ['required', 'integer'],
            'active' => ['nullable', 'boolean'],
        ]);
        $template = WhatsAppTemplate::findOrFail($data['whatsapp_template_id']);
        $service->validateTemplate($data['event_key'], $template);

        $automation = MessageAutomation::updateOrCreate(
            ['business_id' => $request->user()->business_id, 'event_key' => $data['event_key']],
            ['whatsapp_template_id' => $template->id, 'active' => $request->boolean('active')],
        );
        $audit->record('message_automation.updated', $automation, after: $automation->only(['event_key', 'whatsapp_template_id', 'active']), request: $request);

        return back()->with('success', 'Automatización actualizada.');
    }

    public function disable(Request $request, string $eventKey, MessageAutomationService $service, AuditService $audit): RedirectResponse
    {
        if (! array_key_exists($eventKey, $service->definitions())) {
            abort(404);
        }

        $automation = MessageAutomation::firstOrNew([
            'business_id' => $request->user()->business_id,
            'event_key' => $eventKey,
        ]);
        $automation->active = false;
        $automation->save();
        $audit->record('message_automation.disabled', $automation, after: ['event_key' => $eventKey, 'active' => false], request: $request);

        return back()->with('success', 'Envío automático desactivado. Las campañas no se ven afectadas.');
    }
}
