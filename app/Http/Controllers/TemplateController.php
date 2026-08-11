<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppAccount;
use App\Models\WhatsAppTemplate;
use App\Services\AuditService;
use App\Services\MessageAutomationService;
use App\Services\WhatsAppTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TemplateController extends Controller
{
    public function store(
        Request $request,
        WhatsAppTemplateService $service,
        AuditService $audit,
    ): RedirectResponse {
        $data = $this->validated($request);
        unset($data['approval_confirmed']);
        $this->ensureUniqueIdentity($data['technical_name'], $data['language']);
        $samples = array_values(array_filter($data['samples'] ?? [], fn ($value) => filled($value)));
        $service->validateVariables($data['body'], $samples);
        $source = WhatsAppAccount::first()?->provider === 'fake' ? 'demo' : 'manual';

        $template = WhatsAppTemplate::create([
            'business_id' => $request->user()->business_id,
            'public_id' => (string) Str::uuid(),
            ...$data,
            'header' => $data['header_type'] === 'none' ? null : ($data['header'] ?? null),
            'variables' => $samples ? range(1, count($samples)) : [],
            'samples' => $samples,
            'registration_source' => $source,
            'status' => 'approved',
        ]);
        $audit->record('whatsapp_template.registered', $template, businessId: $template->business_id, userId: $request->user()->id);

        return redirect()->to(route('settings.index').'#plantillas')->with('success', 'Plantilla registrada. Ya está disponible para asignarla dentro de la plataforma.');
    }

    public function update(Request $request, string $template, WhatsAppTemplateService $service, MessageAutomationService $automations, AuditService $audit): RedirectResponse
    {
        $template = WhatsAppTemplate::where('public_id', $template)->firstOrFail();
        if ($template->campaigns()->exists()) {
            throw ValidationException::withMessages([
                'template' => 'Esta plantilla ya fue usada en una campaña. Para cambiar el mensaje, crea una nueva versión en WhatsApp Manager y regístrala como una plantilla nueva.',
            ]);
        }

        $data = $this->validated($request);
        unset($data['approval_confirmed']);
        $this->ensureUniqueIdentity($data['technical_name'], $data['language'], $template->id);
        $samples = array_values(array_filter($data['samples'] ?? [], fn ($value) => filled($value)));
        $service->validateVariables($data['body'], $samples);
        foreach ($template->automations as $automation) {
            try {
                $automations->validateTemplate($automation->event_key, $template->replicate()->fill([
                    ...$data,
                    'variables' => $samples ? range(1, count($samples)) : [],
                    'samples' => $samples,
                    'status' => 'approved',
                ]));
            } catch (\DomainException $exception) {
                throw ValidationException::withMessages(['body' => $exception->getMessage()]);
            }
        }
        $before = $template->only(['display_name', 'technical_name', 'category', 'body', 'status']);
        $template->update([
            ...$data,
            'header' => $data['header_type'] === 'none' ? null : ($data['header'] ?? null),
            'variables' => $samples ? range(1, count($samples)) : [],
            'samples' => $samples,
            'rejection_reason' => null,
        ]);
        $audit->record('whatsapp_template.updated', $template, before: $before, after: $template->only(['display_name', 'technical_name', 'category', 'body', 'status']), request: $request);

        return redirect()->to(route('settings.index').'#plantillas')->with('success', 'Registro actualizado. Comprueba que siga coincidiendo exactamente con WhatsApp Manager.');
    }

    public function status(Request $request, string $template, AuditService $audit): RedirectResponse
    {
        $template = WhatsAppTemplate::where('public_id', $template)->firstOrFail();
        $data = $request->validate(['action' => ['required', Rule::in(['enable', 'disable'])]]);
        $before = ['status' => $template->status];
        $enabled = $data['action'] === 'enable';
        $template->update(['status' => $enabled ? 'approved' : 'disabled']);
        $disabledAutomations = 0;
        if (! $enabled) {
            $disabledAutomations = $template->automations()->where('active', true)->update(['active' => false]);
        }
        $audit->record('whatsapp_template.status_changed', $template, before: $before, after: [
            'status' => $template->status,
            'automations_disabled' => $disabledAutomations,
        ], request: $request);

        return back()->with('success', $enabled
            ? 'Plantilla habilitada nuevamente.'
            : 'Plantilla desactivada. Ya no aparecerá para campañas o automatizaciones nuevas.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'display_name' => ['required', 'string', 'max:120'],
            'technical_name' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9_]+$/'],
            'category' => ['required', Rule::in(['utility', 'marketing'])],
            'language' => ['required', 'string', 'max:10'],
            'header_type' => ['required', Rule::in(['none', 'text', 'image'])],
            'header' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:1024'],
            'footer' => ['nullable', 'string', 'max:60'],
            'meta_id' => ['nullable', 'string', 'max:120'],
            'samples' => ['nullable', 'array', 'max:10'],
            'samples.*' => ['nullable', 'string', 'max:120'],
            'approval_confirmed' => ['accepted'],
        ], [
            'approval_confirmed.accepted' => 'Confirma que la plantilla ya está activa en WhatsApp Manager.',
        ]);
    }

    private function ensureUniqueIdentity(string $technicalName, string $language, ?int $exceptId = null): void
    {
        $exists = WhatsAppTemplate::where('technical_name', $technicalName)
            ->where('language', $language)
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages([
                'technical_name' => 'Ya registraste una plantilla de Meta con este nombre e idioma.',
            ]);
        }
    }
}
