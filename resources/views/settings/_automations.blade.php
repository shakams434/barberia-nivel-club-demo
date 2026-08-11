@php
    $compatibleAutomationTemplates = function (array $definition) use ($utilityTemplates) {
        return $utilityTemplates->filter(function ($template) use ($definition) {
            preg_match_all('/\{\{(\d+)\}\}/', $template->body, $matches);

            return collect($matches[1])->unique()->count() === count($definition['variables']);
        });
    };
    $automationTotal = count($automationDefinitions);
    $automationActive = collect($automationDefinitions)->filter(function ($definition, $eventKey) use ($automations) {
        $automation = $automations->get($eventKey);

        return $automation ? $automation->active : $definition['default_enabled'];
    })->count();
    $automationInactive = $automationTotal - $automationActive;
    $availableAutomationCount = count($availableAutomationDefinitions);
@endphp

<section id="automatizaciones" class="card scroll-mt-32">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="eyebrow">Forma de envío</p>
            <h2 class="mt-1 text-xl font-black">Automatizaciones</h2>
            <p class="subtitle max-w-3xl">Aquí decides qué mensaje de servicio se envía cuando ocurre una acción real, por ejemplo registrar un cliente o confirmar un canje.</p>
        </div>
        @if($availableAutomationCount > 0)
            <a class="btn btn-primary shrink-0" href="#anadir-automatizacion">+ Añadir automatización</a>
        @endif
    </div>

    <div class="mt-4 grid grid-cols-3 gap-2 sm:max-w-xl">
        <div class="rounded-xl border border-white/8 p-3"><strong class="block text-lg">{{ $automationTotal }}</strong><span class="text-[11px] text-[#858b95]">acciones configuradas</span></div>
        <div class="rounded-xl border border-emerald-300/15 bg-emerald-300/[0.03] p-3"><strong class="block text-lg text-emerald-200">{{ $automationActive }}</strong><span class="text-[11px] text-[#858b95]">activas</span></div>
        <div class="rounded-xl border border-white/8 p-3"><strong class="block text-lg">{{ $automationInactive }}</strong><span class="text-[11px] text-[#858b95]">desactivadas</span></div>
    </div>

    <div class="mt-4 rounded-xl border border-sky-300/15 bg-sky-300/5 p-3 text-xs leading-5 text-sky-100/80">
        <strong>¿Por qué aquí hay {{ $automationTotal }} y arriba puede haber más plantillas?</strong> Una plantilla es el contenido del mensaje. Una automatización es la acción que lo dispara. Las promociones no aparecen aquí porque se envían desde Campañas, con audiencia, consentimiento y fecha de envío.
    </div>

    <div class="mt-5 grid gap-3 lg:grid-cols-2">
        @foreach($automationDefinitions as $eventKey => $definition)
            @php
                $automation = $automations->get($eventKey);
                $active = $automation ? $automation->active : $definition['default_enabled'];
                $selectedTemplateId = $automation?->whatsapp_template_id ?? $utilityTemplates->firstWhere('technical_name', $definition['default_template'])?->id;
                $compatibleTemplates = $compatibleAutomationTemplates($definition);
            @endphp
            <article class="rounded-2xl border {{ $active ? 'border-emerald-300/15 bg-emerald-300/[0.03]' : 'border-white/8' }} p-4">
                <form method="POST" action="{{ route('automations.update') }}" class="flex h-full flex-col">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="event_key" value="{{ $eventKey }}">

                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="font-black">{{ $definition['name'] }}</h3>
                            <p class="mt-1 text-xs leading-5 text-[#8f959f]">{{ $definition['trigger'] }}</p>
                        </div>
                        <span class="badge {{ $active ? 'badge-success' : 'badge-neutral' }}">{{ $active ? 'Activo' : 'Desactivado' }}</span>
                    </div>

                    <div class="mt-4">
                        <label class="label" for="automation_{{ $eventKey }}">Mensaje que recibirá el cliente</label>
                        <select class="select" id="automation_{{ $eventKey }}" name="whatsapp_template_id" required>
                            @forelse($compatibleTemplates as $template)
                                <option value="{{ $template->id }}" @selected((string) $selectedTemplateId === (string) $template->id)>{{ $template->display_name ?: $template->technical_name }} · {{ $templateStatusLabels[$template->status] ?? $template->status }}</option>
                            @empty
                                <option value="">No hay una plantilla compatible</option>
                            @endforelse
                        </select>
                    </div>

                    <details class="mt-3 rounded-xl border border-white/8 p-3">
                        <summary class="cursor-pointer text-xs font-black">Ver datos automáticos del mensaje</summary>
                        <ol class="mt-2 space-y-1 text-xs text-[#858b95]">
                            @foreach($definition['variables'] as $index => $variable)
                                <li><code>&#123;&#123;{{ $index + 1 }}&#125;&#125;</code> · {{ $variable }}</li>
                            @endforeach
                        </ol>
                    </details>

                    <div class="mt-auto pt-4">
                        <label class="flex min-h-12 cursor-pointer items-center justify-between gap-4 rounded-xl border border-white/10 px-3 py-2 text-sm">
                            <span><strong class="block">Enviar automáticamente</strong><span class="mt-0.5 block text-[11px] text-[#858b95]">{{ $active ? 'Está activo. Desmárcalo para detener este envío.' : 'Márcalo y guarda para activar este envío.' }}</span></span>
                            <input class="checkbox shrink-0" type="checkbox" name="active" value="1" @checked($active)>
                        </label>
                        <button class="btn btn-secondary mt-3 w-full" type="submit" @disabled($compatibleTemplates->isEmpty())>Guardar configuración</button>
                    </div>
                </form>
            </article>
        @endforeach
    </div>

    @if($availableAutomationCount > 0)
        <div id="anadir-automatizacion" class="mt-6 scroll-mt-32 rounded-2xl border border-dashed border-[#d7b52e]/35 bg-[#d7b52e]/[0.04] p-4 sm:p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="eyebrow">Nueva relación</p>
                    <h3 class="mt-1 text-lg font-black">Añadir automatización</h3>
                    <p class="mt-1 max-w-3xl text-xs leading-5 text-[#9aa0aa]">Elige una acción disponible y la plantilla que se enviará. Para agregar otros tipos, como cumpleaños, citas o clientes inactivos, primero debe existir esa función o dato en la plataforma.</p>
                </div>
                <span class="badge badge-neutral">{{ $availableAutomationCount }} disponible{{ $availableAutomationCount === 1 ? '' : 's' }}</span>
            </div>

            <div class="mt-4 grid gap-3">
                @foreach($availableAutomationDefinitions as $eventKey => $definition)
                    @php($compatibleTemplates = $compatibleAutomationTemplates($definition))
                    <form method="POST" action="{{ route('automations.update') }}" class="rounded-xl border border-white/10 bg-black/10 p-4">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="event_key" value="{{ $eventKey }}">
                        <input type="hidden" name="active" value="1">
                        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(260px,0.75fr)_auto] lg:items-end">
                            <div><h4 class="font-black">{{ $definition['name'] }}</h4><p class="mt-1 text-xs leading-5 text-[#8f959f]">{{ $definition['trigger'] }}</p></div>
                            <div>
                                <label class="label" for="new_automation_{{ $eventKey }}">Mensaje compatible</label>
                                <select class="select" id="new_automation_{{ $eventKey }}" name="whatsapp_template_id" required>
                                    @forelse($compatibleTemplates as $template)
                                        <option value="{{ $template->id }}" @selected($template->technical_name === $definition['default_template'])>{{ $template->display_name ?: $template->technical_name }} · {{ $templateStatusLabels[$template->status] ?? $template->status }}</option>
                                    @empty
                                        <option value="">Primero crea una plantilla con {{ count($definition['variables']) }} variables</option>
                                    @endforelse
                                </select>
                            </div>
                            <button class="btn btn-primary w-full lg:w-auto" type="submit" @disabled($compatibleTemplates->isEmpty())>Añadir y activar</button>
                        </div>
                    </form>
                @endforeach
            </div>
        </div>
    @endif
</section>
