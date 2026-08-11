@php
    $compatibleAutomationTemplates = function (array $definition) use ($utilityTemplates) {
        return $utilityTemplates->filter(function ($template) use ($definition) {
            preg_match_all('/\{\{(\d+)\}\}/', $template->body, $matches);

            return collect($matches[1])->unique()->count() === count($definition['variables']);
        });
    };
@endphp

<section id="automatizaciones" class="card scroll-mt-32">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="eyebrow">Cuándo se envía</p>
            <h2 class="mt-1 text-xl font-black">Mensajes automáticos</h2>
            <p class="subtitle max-w-3xl">Conecta una acción real de la plataforma con la plantilla que recibirá el cliente. Las campañas promocionales se administran aparte en Campañas.</p>
        </div>
        <a class="btn btn-primary shrink-0" href="#anadir-automatizacion">+ Añadir automatización</a>
    </div>

    <div class="mt-4 rounded-xl border border-sky-300/15 bg-sky-300/5 p-3 text-xs leading-5 text-sky-100/80">
        <strong>Ejemplo:</strong> “Al registrar una atención” es la acción; “Resumen después de una atención” es la plantilla. Solo se ofrecen acciones que el sistema puede detectar y ejecutar de verdad.
    </div>

    <div class="mt-5 grid gap-4 lg:grid-cols-2">
        @foreach($automationDefinitions as $eventKey => $definition)
            @php
                $automation = $automations->get($eventKey);
                $active = $automation ? $automation->active : $definition['default_enabled'];
                $selectedTemplateId = $automation?->whatsapp_template_id ?? $utilityTemplates->firstWhere('technical_name', $definition['default_template'])?->id;
                $compatibleTemplates = $compatibleAutomationTemplates($definition);
            @endphp
            <article class="rounded-2xl border {{ $active ? 'border-emerald-300/15 bg-emerald-300/[0.03]' : 'border-white/8' }} p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="font-black">{{ $definition['name'] }}</h3>
                        <p class="mt-1 text-xs leading-5 text-[#8f959f]">{{ $definition['trigger'] }}</p>
                    </div>
                    <span class="badge {{ $active ? 'badge-success' : 'badge-neutral' }}">{{ $active ? 'Activo' : 'Desactivado' }}</span>
                </div>

                <details class="mt-3 rounded-xl border border-white/8 p-3">
                    <summary class="cursor-pointer text-xs font-black">Datos que entrega esta acción</summary>
                    <ol class="mt-2 space-y-1 text-xs text-[#858b95]">
                        @foreach($definition['variables'] as $index => $variable)
                            <li><code>&#123;&#123;{{ $index + 1 }}&#125;&#125;</code> · {{ $variable }}</li>
                        @endforeach
                    </ol>
                </details>

                <form method="POST" action="{{ route('automations.update') }}" class="mt-4 space-y-3">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="event_key" value="{{ $eventKey }}">
                    <div>
                        <label class="label" for="automation_{{ $eventKey }}">Plantilla que se enviará</label>
                        <select class="select" id="automation_{{ $eventKey }}" name="whatsapp_template_id" required>
                            @forelse($compatibleTemplates as $template)
                                <option value="{{ $template->id }}" @selected((string) $selectedTemplateId === (string) $template->id)>{{ $template->display_name ?: $template->technical_name }} · {{ $templateStatusLabels[$template->status] ?? $template->status }}</option>
                            @empty
                                <option value="">No hay una plantilla compatible</option>
                            @endforelse
                        </select>
                    </div>
                    <label class="flex min-h-11 items-center gap-3 text-sm"><input class="checkbox" type="checkbox" name="active" value="1" @checked($active)> Enviar automáticamente</label>
                    <button class="btn btn-secondary w-full" type="submit" @disabled($compatibleTemplates->isEmpty())>Guardar cambios</button>
                </form>

                @if($active)
                    <form method="POST" action="{{ route('automations.disable', $eventKey) }}" class="mt-2" data-confirm="Se dejará de enviar este mensaje automático. La plantilla no se borrará y las campañas no cambiarán." data-confirm-title="Desactivar envío automático" data-confirm-button="Desactivar">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-ghost w-full text-xs" type="submit">Desactivar envío</button>
                    </form>
                @endif
            </article>
        @endforeach
    </div>

    <div id="anadir-automatizacion" class="mt-6 scroll-mt-32 rounded-2xl border border-dashed border-[#d7b52e]/35 bg-[#d7b52e]/[0.04] p-4 sm:p-5">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="eyebrow">Nueva relación</p>
                <h3 class="mt-1 text-lg font-black">Añadir automatización</h3>
                <p class="mt-1 max-w-3xl text-xs leading-5 text-[#9aa0aa]">Elige una acción disponible y la plantilla que se enviará. Para agregar otros tipos, como cumpleaños, citas o clientes inactivos, primero debe existir esa función o dato en la plataforma.</p>
            </div>
            <span class="badge badge-neutral">{{ count($availableAutomationDefinitions) }} disponible{{ count($availableAutomationDefinitions) === 1 ? '' : 's' }}</span>
        </div>

        <div class="mt-4 grid gap-3">
            @forelse($availableAutomationDefinitions as $eventKey => $definition)
                @php($compatibleTemplates = $compatibleAutomationTemplates($definition))
                <form method="POST" action="{{ route('automations.update') }}" class="rounded-xl border border-white/10 bg-black/10 p-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="event_key" value="{{ $eventKey }}">
                    <input type="hidden" name="active" value="1">
                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(260px,0.75fr)_auto] lg:items-end">
                        <div>
                            <h4 class="font-black">{{ $definition['name'] }}</h4>
                            <p class="mt-1 text-xs leading-5 text-[#8f959f]">{{ $definition['trigger'] }}</p>
                        </div>
                        <div>
                            <label class="label" for="new_automation_{{ $eventKey }}">Plantilla compatible</label>
                            <select class="select" id="new_automation_{{ $eventKey }}" name="whatsapp_template_id" required>
                                @forelse($compatibleTemplates as $template)
                                    <option value="{{ $template->id }}" @selected($template->technical_name === $definition['default_template'])>{{ $template->display_name ?: $template->technical_name }} · {{ $templateStatusLabels[$template->status] ?? $template->status }}</option>
                                @empty
                                    <option value="">Primero crea una plantilla con {{ count($definition['variables']) }} variables</option>
                                @endforelse
                            </select>
                        </div>
                        <button class="btn btn-primary w-full lg:w-auto" type="submit" @disabled($compatibleTemplates->isEmpty())>Añadir</button>
                    </div>
                    <p class="mt-3 text-[11px] text-[#757b85]">Usará {{ count($definition['variables']) }} datos: {{ implode(', ', $definition['variables']) }}.</p>
                </form>
            @empty
                <div class="rounded-xl border border-white/8 p-4 text-sm text-[#9aa0aa]">Ya configuraste todas las acciones compatibles disponibles.</div>
            @endforelse
        </div>
    </div>
</section>
