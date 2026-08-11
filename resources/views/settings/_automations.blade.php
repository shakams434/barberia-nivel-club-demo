<section id="automatizaciones" class="card scroll-mt-32">
    <div><p class="eyebrow">Cuándo se envía</p><h2 class="mt-1 text-xl font-black">Mensajes automáticos</h2><p class="subtitle max-w-3xl">Aquí conectas una acción del programa con una plantilla. Las campañas promocionales son manuales y se administran aparte en Campañas.</p></div>
    <div class="mt-4 rounded-xl border border-sky-300/15 bg-sky-300/5 p-3 text-xs leading-5 text-sky-100/80"><strong>Ejemplo:</strong> “Al registrar una atención” es la acción; “Resumen después de una atención” es la plantilla que recibe el cliente. Puedes cambiarla o desactivar ese envío sin borrar la plantilla.</div>

    <div class="mt-5 grid gap-4 lg:grid-cols-2">
        @foreach($automationDefinitions as $eventKey => $definition)
            @php
                $automation = $automations->get($eventKey);
                $active = $automation ? $automation->active : true;
                $selectedTemplateId = $automation?->whatsapp_template_id ?? $utilityTemplates->firstWhere('technical_name', $definition['default_template'])?->id;
                $compatibleTemplates = $utilityTemplates->filter(function ($template) use ($definition) {
                    preg_match_all('/\{\{(\d+)\}\}/', $template->body, $matches);
                    return collect($matches[1])->unique()->count() === count($definition['variables']);
                });
            @endphp
            <article class="rounded-2xl border {{ $active ? 'border-emerald-300/15 bg-emerald-300/[0.03]' : 'border-white/8' }} p-4">
                <div class="flex items-start justify-between gap-3"><div><h3 class="font-black">{{ $definition['name'] }}</h3><p class="mt-1 text-xs leading-5 text-[#8f959f]">{{ $definition['trigger'] }}</p></div><span class="badge {{ $active ? 'badge-success' : 'badge-neutral' }}">{{ $active ? 'Activo' : 'Desactivado' }}</span></div>
                <details class="mt-3 rounded-xl border border-white/8 p-3"><summary class="cursor-pointer text-xs font-black">Datos que entrega esta acción</summary><ol class="mt-2 space-y-1 text-xs text-[#858b95]">
                    @foreach($definition['variables'] as $index => $variable)
                        <li><code>&#123;&#123;{{ $index + 1 }}&#125;&#125;</code> · {{ $variable }}</li>
                    @endforeach
                </ol></details>
                <form method="POST" action="{{ route('automations.update') }}" class="mt-4 space-y-3">@csrf @method('PUT')<input type="hidden" name="event_key" value="{{ $eventKey }}"><div><label class="label" for="automation_{{ $eventKey }}">Plantilla que se enviará</label><select class="select" id="automation_{{ $eventKey }}" name="whatsapp_template_id" required>@forelse($compatibleTemplates as $template)<option value="{{ $template->id }}" @selected((string) $selectedTemplateId === (string) $template->id)>{{ $template->display_name ?: $template->technical_name }} · {{ $templateStatusLabels[$template->status] ?? $template->status }}</option>@empty<option value="">No hay una plantilla compatible</option>@endforelse</select></div><label class="flex min-h-11 items-center gap-3 text-sm"><input class="checkbox" type="checkbox" name="active" value="1" @checked($active)> Enviar automáticamente</label><button class="btn btn-secondary w-full" type="submit" @disabled($compatibleTemplates->isEmpty())>Guardar relación</button></form>
                @if($active)<form method="POST" action="{{ route('automations.disable', $eventKey) }}" class="mt-2" data-confirm="Se dejará de enviar este mensaje automático. La plantilla no se borrará y las campañas no cambiarán." data-confirm-title="Desactivar envío automático" data-confirm-button="Desactivar">@csrf @method('DELETE')<button class="btn btn-ghost w-full text-xs" type="submit">Quitar relación y desactivar</button></form>@endif
            </article>
        @endforeach
    </div>
</section>
