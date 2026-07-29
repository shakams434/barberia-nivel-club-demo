<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppTemplate;
use App\Services\AuditService;
use App\Services\WhatsAppTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TemplateController extends Controller
{
    public function store(Request $request, WhatsAppTemplateService $service, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'technical_name' => ['required', 'alpha_dash', 'max:120'],
            'category' => ['required', Rule::in(['utility', 'marketing'])],
            'language' => ['required', 'string', 'max:10'],
            'header_type' => ['required', Rule::in(['none', 'text', 'image'])],
            'header' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:1024'],
            'footer' => ['nullable', 'string', 'max:60'],
            'samples' => ['nullable', 'array'],
        ]);
        $samples = array_values(array_filter($data['samples'] ?? [], fn ($value) => filled($value)));
        $service->validateVariables($data['body'], $samples);
        $template = WhatsAppTemplate::create([
            'business_id' => $request->user()->business_id,
            'public_id' => (string) Str::uuid(),
            ...$data,
            'variables' => array_keys($samples),
            'samples' => $samples,
            'status' => 'draft',
        ]);
        $audit->record('whatsapp_template.created', $template, businessId: $template->business_id, userId: $request->user()->id);

        return back()->with('success', 'Borrador de plantilla creado.');
    }

    public function review(Request $request, string $template, WhatsAppTemplateService $service): RedirectResponse
    {
        $template = WhatsAppTemplate::where('public_id', $template)->firstOrFail();
        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject'])], 'reason' => ['nullable', 'string', 'max:500']]);
        $service->simulateReview($template, $data['decision'] === 'approve', $data['reason'] ?? null);

        return back()->with('success', 'Estado local de la plantilla actualizado.');
    }

    public function submit(string $template, WhatsAppTemplateService $service): RedirectResponse
    {
        $service->submitToMeta(WhatsAppTemplate::where('public_id', $template)->firstOrFail());

        return back()->with('success', 'Plantilla enviada a Meta. La clasificación final depende de su revisión.');
    }

    public function sync(string $template, WhatsAppTemplateService $service): RedirectResponse
    {
        $service->syncFromMeta(WhatsAppTemplate::where('public_id', $template)->firstOrFail());

        return back()->with('success', 'Estado sincronizado con Meta.');
    }
}
