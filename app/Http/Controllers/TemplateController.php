<?php

namespace App\Http\Controllers;

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
        MessageAutomationService $automations,
        AuditService $audit,
    ): RedirectResponse {
        $data = $this->validated($request);
        $source = filled($data['replaces_template'] ?? null)
            ? WhatsAppTemplate::where('public_id', $data['replaces_template'])->firstOrFail()
            : null;
        unset($data['replaces_template']);
        $technicalName = $data['technical_name'] ?: Str::slug($data['display_name'], '_');
        if (WhatsAppTemplate::where('technical_name', $technicalName)->where('language', $data['language'])->exists()) {
            $technicalName .= '_'.Str::lower(Str::random(4));
        }
        $samples = array_values(array_filter($data['samples'] ?? [], fn ($value) => filled($value)));
        $service->validateVariables($data['body'], $samples);
        if ($source) {
            $this->validateReplacement($source, $data, $samples, $automations);
        }

        $template = WhatsAppTemplate::create([
            'business_id' => $request->user()->business_id,
            'replaces_template_id' => $source?->id,
            'public_id' => (string) Str::uuid(),
            ...$data,
            'technical_name' => $technicalName,
            'header' => $data['header_type'] === 'none' ? null : ($data['header'] ?? null),
            'variables' => $samples ? range(1, count($samples)) : [],
            'samples' => $samples,
            'status' => 'draft',
        ]);
        $audit->record('whatsapp_template.created', $template, businessId: $template->business_id, userId: $request->user()->id);

        return redirect()->to(route('settings.index').'#plantillas')->with(
            'success',
            $source
                ? 'Nueva versión guardada como borrador. La versión actual seguirá activa hasta que apruebes esta.'
                : 'Borrador creado. Ahora puedes revisarlo y asignarlo a una automatización cuando esté aprobado.'
        );
    }

    public function update(Request $request, string $template, WhatsAppTemplateService $service, AuditService $audit): RedirectResponse
    {
        $template = WhatsAppTemplate::where('public_id', $template)->firstOrFail();
        if (in_array($template->status, ['approved', 'pending'], true)) {
            throw ValidationException::withMessages([
                'template' => 'Meta no permite editar una plantilla aprobada o en revisión. Crea una nueva versión y luego reasigna la automatización.',
            ]);
        }

        $data = $this->validated($request);
        $samples = array_values(array_filter($data['samples'] ?? [], fn ($value) => filled($value)));
        $service->validateVariables($data['body'], $samples);
        $before = $template->only(['display_name', 'technical_name', 'category', 'body', 'status']);
        $template->update([
            ...$data,
            'header' => $data['header_type'] === 'none' ? null : ($data['header'] ?? null),
            'variables' => $samples ? range(1, count($samples)) : [],
            'samples' => $samples,
            'status' => 'draft',
            'rejection_reason' => null,
        ]);
        $audit->record('whatsapp_template.updated', $template, before: $before, after: $template->only(['display_name', 'technical_name', 'category', 'body', 'status']), request: $request);

        return redirect()->to(route('settings.index').'#plantillas')->with('success', 'Borrador de plantilla actualizado.');
    }

    public function review(
        Request $request,
        string $template,
        WhatsAppTemplateService $service,
        MessageAutomationService $automations,
        AuditService $audit,
    ): RedirectResponse {
        $template = WhatsAppTemplate::where('public_id', $template)->firstOrFail();
        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject'])], 'reason' => ['nullable', 'string', 'max:500']]);
        $service->simulateReview($template, $data['decision'] === 'approve', $data['reason'] ?? null);
        $reassigned = $data['decision'] === 'approve'
            ? $this->activateReplacement($template->fresh(), $automations, $audit, $request)
            : 0;

        return back()->with('success', $reassigned > 0
            ? "Plantilla aprobada y activada en {$reassigned} mensaje(s) automático(s)."
            : 'Estado local de la plantilla actualizado.');
    }

    public function submit(string $template, WhatsAppTemplateService $service): RedirectResponse
    {
        $service->submitToMeta(WhatsAppTemplate::where('public_id', $template)->firstOrFail());

        return back()->with('success', 'Plantilla enviada a Meta. La clasificación final depende de su revisión.');
    }

    public function sync(
        Request $request,
        string $template,
        WhatsAppTemplateService $service,
        MessageAutomationService $automations,
        AuditService $audit,
    ): RedirectResponse {
        $template = WhatsAppTemplate::where('public_id', $template)->firstOrFail();
        $service->syncFromMeta($template);
        $reassigned = $template->fresh()->status === 'approved'
            ? $this->activateReplacement($template->fresh(), $automations, $audit, $request)
            : 0;

        return back()->with('success', $reassigned > 0
            ? "Estado sincronizado y nueva versión activada en {$reassigned} mensaje(s) automático(s)."
            : 'Estado sincronizado con Meta.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'display_name' => ['required', 'string', 'max:120'],
            'technical_name' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9_]+$/'],
            'category' => ['required', Rule::in(['utility', 'marketing'])],
            'language' => ['required', 'string', 'max:10'],
            'header_type' => ['required', Rule::in(['none', 'text', 'image'])],
            'header' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:1024'],
            'footer' => ['nullable', 'string', 'max:60'],
            'samples' => ['nullable', 'array', 'max:10'],
            'samples.*' => ['nullable', 'string', 'max:120'],
            'replaces_template' => ['nullable', 'uuid'],
        ]);
    }

    private function validateReplacement(
        WhatsAppTemplate $source,
        array $data,
        array $samples,
        MessageAutomationService $automations,
    ): void {
        if ($source->category !== $data['category']) {
            throw ValidationException::withMessages([
                'category' => 'Una nueva versión debe conservar el tipo de mensaje de la plantilla actual.',
            ]);
        }

        foreach ($source->automations as $automation) {
            $expected = count($automations->definitions()[$automation->event_key]['variables'] ?? []);
            if ($expected !== count($samples)) {
                throw ValidationException::withMessages([
                    'body' => "Esta automatización necesita exactamente {$expected} datos dinámicos. Puedes cambiar el texto, pero no quitar ni agregar esos datos.",
                ]);
            }
        }
    }

    private function activateReplacement(
        WhatsAppTemplate $template,
        MessageAutomationService $automations,
        AuditService $audit,
        Request $request,
    ): int {
        if (! $template->replaces_template_id) {
            return 0;
        }

        $reassigned = 0;
        $source = $template->replacesTemplate()->with('automations')->first();
        foreach ($source?->automations ?? [] as $automation) {
            try {
                $automations->validateTemplate($automation->event_key, $template);
            } catch (\DomainException) {
                continue;
            }

            $automation->update(['whatsapp_template_id' => $template->id]);
            $reassigned++;
        }

        if ($reassigned > 0) {
            $audit->record(
                'whatsapp_template.version_activated',
                $template,
                after: ['replaces_template_id' => $template->replaces_template_id, 'automations_reassigned' => $reassigned],
                request: $request,
            );
        }

        return $reassigned;
    }
}
