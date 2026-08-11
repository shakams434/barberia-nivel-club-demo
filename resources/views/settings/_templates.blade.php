@php
    $templateValue = fn ($field, $default = '') => old($field, $editingTemplate?->{$field} ?? $default);
    $availableTemplates = $templates->where('status', 'approved');
@endphp

<section id="plantillas" class="card scroll-mt-32">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <p class="eyebrow">Contenido de WhatsApp</p>
            <h2 class="mt-1 text-xl font-black">Plantillas registradas</h2>
            <p class="subtitle max-w-3xl">Meta aprueba las plantillas en WhatsApp Manager. Aquí solo registras una plantilla que ya está activa para usarla en campañas o mensajes automáticos.</p>
        </div>
        <a class="btn btn-primary shrink-0" href="#registrar-plantilla">＋ Registrar plantilla aprobada</a>
    </div>

    <div class="mt-5 grid gap-3 md:grid-cols-3">
        @foreach([
            ['number' => '1', 'title' => 'Crear y aprobar en Meta', 'text' => 'Hazlo en WhatsApp Manager, fuera de esta plataforma.'],
            ['number' => '2', 'title' => 'Registrar aquí', 'text' => 'Copia exactamente el nombre, idioma, contenido y variables aprobados.'],
            ['number' => '3', 'title' => 'Elegir dónde se usa', 'text' => 'Asígnala a una campaña o a una acción automática compatible.'],
        ] as $step)
            <article class="rounded-2xl border border-white/8 p-4">
                <div class="flex items-center gap-2"><span class="grid h-7 w-7 place-items-center rounded-full border border-white/10 text-xs font-black">{{ $step['number'] }}</span><strong class="text-sm">{{ $step['title'] }}</strong></div>
                <p class="mt-2 text-xs leading-5 text-[#858b95]">{{ $step['text'] }}</p>
            </article>
        @endforeach
    </div>

    <div class="mt-4 flex flex-col gap-3 rounded-xl border border-sky-300/15 bg-sky-300/5 p-4 text-xs leading-5 text-sky-100/80 sm:flex-row sm:items-center sm:justify-between">
        <p><strong>Importante:</strong> registrar una plantilla aquí no la envía ni la aprueba en Meta. El administrador confirma que ya está activa en WhatsApp Manager.</p>
        <a class="btn btn-secondary shrink-0" href="https://business.facebook.com/wa/manage/message-templates/" target="_blank" rel="noopener noreferrer">Abrir WhatsApp Manager ↗</a>
    </div>

    <div class="mt-5 grid gap-3 sm:grid-cols-2">
        <div class="rounded-xl border border-emerald-300/15 bg-emerald-300/[0.03] p-4"><span class="text-2xl font-black">{{ $availableTemplates->where('category', 'utility')->count() }}</span><strong class="ml-2 text-sm">de servicio disponibles</strong><p class="mt-2 text-xs leading-5 text-[#858b95]">Se conectan a bienvenidas, atenciones, niveles o canjes.</p></div>
        <div class="rounded-xl border border-[#d7b52e]/20 bg-[#d7b52e]/[0.04] p-4"><span class="text-2xl font-black">{{ $availableTemplates->where('category', 'marketing')->count() }}</span><strong class="ml-2 text-sm">promocionales disponibles</strong><p class="mt-2 text-xs leading-5 text-[#858b95]">Una misma plantilla puede reutilizarse en varias campañas.</p></div>
    </div>

    @foreach([
        ['category' => 'utility', 'title' => 'Mensajes de servicio', 'description' => 'Plantillas para acciones automáticas del programa.'],
        ['category' => 'marketing', 'title' => 'Mensajes para campañas', 'description' => 'Contenido promocional reutilizable; no son campañas creadas.'],
    ] as $group)
        <div class="mt-6 flex items-end justify-between gap-3">
            <div><h3 class="font-black">{{ $group['title'] }}</h3><p class="mt-1 text-xs leading-5 text-[#858b95]">{{ $group['description'] }}</p></div>
            <span class="badge badge-neutral">{{ $templates->where('category', $group['category'])->count() }}</span>
        </div>

        <div class="mt-3 grid gap-3 lg:grid-cols-2">
            @forelse($templates->where('category', $group['category']) as $template)
                @php
                    $renderedBody = $template->body;
                    foreach(array_values($template->samples ?? []) as $index => $sample) $renderedBody = str_replace('{{'.($index + 1).'}}', $sample, $renderedBody);
                    $activeUses = $template->automations->where('active', true)->pluck('event_key');
                    $sourceLabel = match($template->registration_source ?? 'manual') {
                        'demo' => 'Disponible en demo',
                        'meta_sync' => 'Verificada con Meta',
                        default => 'Registrada manualmente',
                    };
                    $isAvailable = $template->status === 'approved';
                @endphp
                <article class="card-soft flex flex-col {{ $isAvailable ? '' : 'opacity-75' }}">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0"><h4 class="font-black">{{ $template->display_name ?: $template->technical_name }}</h4><p class="mt-1 break-all text-[11px] text-[#737984]">Meta: {{ $template->technical_name }} · {{ $template->language }}</p></div>
                        <span class="badge {{ $isAvailable ? 'badge-success' : 'badge-neutral' }}">{{ $isAvailable ? $sourceLabel : 'Desactivada' }}</span>
                    </div>

                    <div class="mt-3 rounded-2xl bg-[#0d1712] p-3"><p class="whitespace-pre-line text-xs leading-5 text-[#d7e7dc]">{{ $renderedBody }}</p></div>

                    <div class="mt-3 flex flex-wrap gap-1.5">
                        <span class="badge badge-neutral">{{ $template->category === 'marketing' ? 'Promocional' : 'Servicio' }}</span>
                        @if($template->meta_id)<span class="badge badge-neutral">ID Meta registrado</span>@endif
                        @foreach($activeUses as $eventKey)<span class="badge badge-success">Automática: {{ $automationDefinitions[$eventKey]['name'] ?? $eventKey }}</span>@endforeach
                        @if($template->category === 'marketing' && $template->campaigns_count > 0)<span class="badge badge-warning">Usada en {{ $template->campaigns_count }} campaña{{ $template->campaigns_count === 1 ? '' : 's' }}</span>@endif
                    </div>

                    <div class="mt-3 rounded-xl border border-white/8 p-3 text-xs leading-5 text-[#8f959f]">
                        @if(!$isAvailable)
                            <strong class="text-white">No disponible:</strong> no puede elegirse para nuevos envíos.
                        @elseif($template->category === 'marketing')
                            <strong class="text-white">Uso:</strong> elige audiencia y fecha desde Campañas. <a class="ml-1 font-black text-[#e2c541]" href="{{ route('campaigns.create') }}">Crear campaña →</a>
                        @elseif($activeUses->isNotEmpty())
                            <strong class="text-white">Uso:</strong> conectada a {{ $activeUses->count() }} acción{{ $activeUses->count() === 1 ? '' : 'es' }} automática{{ $activeUses->count() === 1 ? '' : 's' }}. <a class="ml-1 font-black text-emerald-200" href="#automatizaciones">Administrar →</a>
                        @else
                            <strong class="text-white">Sin asignar:</strong> puedes conectarla desde Automatizaciones. <a class="ml-1 font-black text-[#e2c541]" href="#automatizaciones">Configurar →</a>
                        @endif
                    </div>

                    <div class="mt-auto flex flex-col gap-2 pt-4 sm:flex-row sm:flex-wrap">
                        @if($template->campaigns_count === 0)
                            <a class="btn btn-ghost min-h-10 text-xs" href="{{ route('settings.index', ['edit_template' => $template->public_id]) }}#registrar-plantilla">Corregir registro</a>
                        @else
                            <span class="self-center text-[11px] leading-5 text-[#777d87]">Para cambiar el mensaje, crea otra versión en Meta y regístrala como nueva.</span>
                        @endif
                        <form method="POST" action="{{ route('templates.status', $template) }}" @if($isAvailable) data-confirm="La plantilla dejará de aparecer en campañas y automatizaciones nuevas. Las automatizaciones activas asociadas se detendrán." data-confirm-title="Desactivar plantilla" data-confirm-button="Desactivar" @endif>
                            @csrf @method('PUT')
                            <input type="hidden" name="action" value="{{ $isAvailable ? 'disable' : 'enable' }}">
                            <button class="btn {{ $isAvailable ? 'btn-ghost' : 'btn-secondary' }} min-h-10 w-full text-xs" type="submit">{{ $isAvailable ? 'Desactivar' : 'Habilitar nuevamente' }}</button>
                        </form>
                    </div>
                </article>
            @empty
                <div class="empty lg:col-span-2">No hay plantillas registradas en esta categoría.</div>
            @endforelse
        </div>
    @endforeach

    <details id="registrar-plantilla" class="mt-6 scroll-mt-32 rounded-2xl border border-[#d7b52e]/25 bg-[#d7b52e]/[0.03] p-4 sm:p-5" @if($editingTemplate || $errors->hasAny(['display_name','technical_name','category','body','samples.*','approval_confirmed','template'])) open @endif>
        <summary class="cursor-pointer font-black">{{ $editingTemplate ? 'Corregir datos registrados' : 'Registrar una plantilla aprobada' }}</summary>
        <p class="mt-2 text-xs leading-5 text-[#8f959f]">{{ $editingTemplate ? 'Corrige únicamente diferencias entre este registro y la plantilla activa en Meta.' : 'Primero créala y espera su aprobación en WhatsApp Manager. Después copia aquí sus datos sin modificar el contenido.' }}</p>

        <form method="POST" action="{{ $editingTemplate ? route('templates.update', $editingTemplate) : route('templates.store') }}" class="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1fr)_360px]" data-template-editor>
            @csrf @if($editingTemplate) @method('PUT') @endif
            <div class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label class="label" for="display_name">Nombre para identificarla aquí</label><input class="input" id="display_name" name="display_name" value="{{ $templateValue('display_name') }}" placeholder="Ej. Promoción por nivel" required data-template-display><p class="mt-1.5 text-[11px] text-[#777d87]">Solo se muestra dentro de esta plataforma.</p></div>
                    <div><label class="label" for="technical_name">Nombre técnico exacto en Meta</label><input class="input" id="technical_name" name="technical_name" value="{{ $templateValue('technical_name') }}" placeholder="campaign_level_discount" required data-template-technical><p class="mt-1.5 text-[11px] text-[#777d87]">Minúsculas, números y guiones bajos.</p></div>
                    <div><label class="label" for="category">Categoría aprobada en Meta</label><select class="select" id="category" name="category"><option value="utility" @selected($templateValue('category', 'utility') === 'utility')>Servicio / Utility</option><option value="marketing" @selected($templateValue('category') === 'marketing')>Promoción / Marketing</option></select></div>
                    <div><label class="label" for="language">Idioma exacto</label><input class="input" id="language" name="language" value="{{ $templateValue('language', 'es_PE') }}" placeholder="es_PE" required></div>
                    <div class="sm:col-span-2"><label class="label" for="meta_id">ID de plantilla en Meta <span class="normal-case text-[#777d87]">(opcional)</span></label><input class="input" id="meta_id" name="meta_id" value="{{ $templateValue('meta_id') }}" placeholder="Si WhatsApp Manager lo muestra"></div>
                </div>

                <div><label class="label" for="body">Texto exacto aprobado en Meta</label><textarea class="textarea min-h-40" id="body" name="body" placeholder="Hola &#123;&#123;1&#125;&#125;. Tu mensaje…" required data-template-body>{{ $templateValue('body') }}</textarea><p class="mt-1.5 text-[11px] text-[#777d87]">Conserva las variables &#123;&#123;1&#125;&#125;, &#123;&#123;2&#125;&#125;… en el mismo orden que en Meta.</p></div>

                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach(range(0, 9) as $i)
                        <div data-template-sample-field><label class="label" for="sample_{{ $i }}">Ejemplo para &#123;&#123;{{ $i + 1 }}&#125;&#125;</label><input class="input" id="sample_{{ $i }}" name="samples[]" value="{{ old("samples.$i", $editingTemplate?->samples[$i] ?? '') }}" placeholder="Valor de prueba"></div>
                    @endforeach
                </div>

                <details class="rounded-xl border border-white/8 p-4"><summary class="cursor-pointer text-sm font-black">Encabezado y pie aprobados</summary><div class="mt-4 grid gap-4 sm:grid-cols-2"><div><label class="label" for="header_type">Tipo de encabezado</label><select class="select" id="header_type" name="header_type"><option value="none" @selected($templateValue('header_type', 'none') === 'none')>Sin encabezado</option><option value="text" @selected($templateValue('header_type') === 'text')>Texto</option><option value="image" @selected($templateValue('header_type') === 'image')>Imagen administrada en Meta</option></select></div><div><label class="label" for="header">Texto del encabezado</label><input class="input" id="header" name="header" value="{{ $templateValue('header') }}"></div><div class="sm:col-span-2"><label class="label" for="footer">Pie</label><input class="input" id="footer" name="footer" value="{{ $templateValue('footer') }}" maxlength="60"></div></div></details>

                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-emerald-300/15 bg-emerald-300/[0.03] p-4 text-sm"><input class="checkbox mt-0.5 shrink-0" type="checkbox" name="approval_confirmed" value="1" required><span><strong class="block">Confirmo que esta plantilla ya está activa en WhatsApp Manager</strong><span class="mt-1 block text-xs leading-5 text-[#8f959f]">La plataforma no la envía a Meta ni puede aprobarla por sí sola.</span></span></label>

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">@if($editingTemplate)<a class="btn btn-ghost" href="{{ route('settings.index') }}#plantillas">Cancelar</a>@endif<button class="btn btn-primary" type="submit">{{ $editingTemplate ? 'Guardar corrección' : 'Registrar y dejar disponible' }}</button></div>
            </div>

            <aside class="h-fit rounded-2xl border border-white/8 bg-black/15 p-4 lg:sticky lg:top-24"><p class="eyebrow">Comprobación</p><h3 class="mt-1 font-black">Vista previa local</h3><div class="mt-4 rounded-[1.6rem] bg-[#0d1712] p-4"><div class="ml-auto min-h-28 whitespace-pre-line rounded-2xl rounded-tr-sm bg-[#1f4f3a] p-3 text-sm leading-6 text-[#edf8f1]" data-template-editor-preview>Escribe el mensaje para ver la vista previa.</div></div><p class="mt-3 text-xs leading-5 text-[#858b95]">Esta vista sirve para detectar errores de copia. El contenido oficial sigue siendo el de WhatsApp Manager.</p></aside>
        </form>
    </details>
</section>
