@php
    $templateSource = $editingTemplate ?? $versioningTemplate;
    $templateValue = function ($field, $default = '') use ($editingTemplate, $versioningTemplate, $versionTechnicalName) {
        $value = $editingTemplate?->{$field} ?? $versioningTemplate?->{$field} ?? $default;
        if ($versioningTemplate && $field === 'display_name') $value = ($versioningTemplate->display_name ?: $versioningTemplate->technical_name).' · nueva versión';
        if ($versioningTemplate && $field === 'technical_name') $value = $versionTechnicalName;

        return old($field, $value);
    };
@endphp
<section id="plantillas" class="card scroll-mt-32">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div><p class="eyebrow">Contenido de WhatsApp</p><h2 class="mt-1 text-xl font-black">Biblioteca de mensajes</h2><p class="subtitle max-w-3xl">Aquí editas lo que leerá el cliente. La forma de envío se configura después: los mensajes de servicio pueden conectarse a una acción automática; las promociones se usan desde Campañas.</p></div>
        <a class="btn btn-secondary" href="#editor-plantilla">＋ Nueva plantilla</a>
    </div>

    <div class="mt-5 grid gap-3 sm:grid-cols-2">
        <a class="rounded-xl border border-emerald-300/15 bg-emerald-300/[0.03] p-4 transition hover:border-emerald-300/30" href="#automatizaciones"><span class="text-2xl font-black">{{ $templates->where('category', 'utility')->count() }}</span><strong class="ml-2 text-sm">mensajes de servicio</strong><p class="mt-2 text-xs leading-5 text-[#858b95]">Pueden enviarse automáticamente cuando ocurre una acción.</p></a>
        <a class="rounded-xl border border-[#d7b52e]/20 bg-[#d7b52e]/[0.04] p-4 transition hover:border-[#d7b52e]/40" href="{{ route('campaigns.index') }}"><span class="text-2xl font-black">{{ $templates->where('category', 'marketing')->count() }}</span><strong class="ml-2 text-sm">promociones</strong><p class="mt-2 text-xs leading-5 text-[#858b95]">Se envían desde Campañas eligiendo audiencia y fecha.</p></a>
    </div>

    @foreach([
        ['category' => 'utility', 'title' => 'Mensajes de servicio', 'description' => 'Contenido disponible para bienvenida, atenciones, niveles, canjes y otras acciones automáticas.'],
        ['category' => 'marketing', 'title' => 'Promociones para campañas', 'description' => 'No se disparan por una acción. Se envían a una audiencia autorizada desde el módulo Campañas.'],
    ] as $group)
        <div class="mt-6 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div><h3 class="font-black">{{ $group['title'] }}</h3><p class="mt-1 text-xs leading-5 text-[#858b95]">{{ $group['description'] }}</p></div>
            <span class="badge badge-neutral">{{ $templates->where('category', $group['category'])->count() }} mensaje{{ $templates->where('category', $group['category'])->count() === 1 ? '' : 's' }}</span>
        </div>
        <div class="mt-3 grid gap-3 lg:grid-cols-2">
        @forelse($templates->where('category', $group['category']) as $template)
            @php
                $renderedBody = $template->body;
                foreach(array_values($template->samples ?? []) as $index => $sample) $renderedBody = str_replace('{{'.($index + 1).'}}', $sample, $renderedBody);
                $activeUses = $template->automations->where('active', true)->pluck('event_key');
                $inactiveUses = $template->automations->where('active', false)->pluck('event_key');
            @endphp
            <article class="card-soft flex flex-col">
                <div class="flex flex-wrap items-start justify-between gap-2"><div class="min-w-0"><h3 class="font-black">{{ $template->display_name ?: $template->technical_name }}</h3><p class="mt-0.5 truncate text-[11px] text-[#737984]">Meta: {{ $template->technical_name }} · {{ $template->language }}</p></div><span class="badge {{ $template->status === 'approved' ? 'badge-success' : ($template->status === 'rejected' ? 'badge-danger' : 'badge-warning') }}">{{ $templateStatusLabels[$template->status] ?? $template->status }}</span></div>
                <div class="mt-3 rounded-2xl bg-[#0d1712] p-3"><p class="whitespace-pre-line text-xs leading-5 text-[#d7e7dc]">{{ $renderedBody }}</p></div>
                <div class="mt-3 flex flex-wrap gap-1.5">
                    @if($template->category === 'marketing')
                        <span class="badge badge-warning">Solo campañas · no automático</span>
                    @else
                        <span class="badge badge-neutral">Mensaje de servicio</span>
                        @foreach($activeUses as $eventKey)<span class="badge badge-success">Automático: {{ $automationDefinitions[$eventKey]['name'] ?? $eventKey }}</span>@endforeach
                        @foreach($inactiveUses as $eventKey)<span class="badge badge-neutral">Automatización desactivada: {{ $automationDefinitions[$eventKey]['name'] ?? $eventKey }}</span>@endforeach
                        @if($activeUses->isEmpty() && $inactiveUses->isEmpty())<span class="badge badge-neutral">Disponible para asignar</span>@endif
                    @endif
                </div>
                <div class="mt-3 rounded-xl border border-white/8 p-3 text-xs leading-5 text-[#8f959f]">
                    @if($template->category === 'marketing')
                        <strong class="text-white">¿Cómo se envía?</strong> Crea una campaña, elige los clientes y programa la fecha. <a class="ml-1 font-black text-[#e2c541]" href="{{ route('campaigns.create') }}">Usar en una campaña →</a>
                    @elseif($activeUses->isNotEmpty())
                        <strong class="text-white">Envío actual:</strong> esta plantilla ya está conectada a {{ $activeUses->count() }} acción{{ $activeUses->count() === 1 ? '' : 'es' }} automática{{ $activeUses->count() === 1 ? '' : 's' }}. <a class="ml-1 font-black text-emerald-200" href="#automatizaciones">Administrar →</a>
                    @elseif($inactiveUses->isNotEmpty())
                        <strong class="text-white">Envío detenido:</strong> puede volver a activarse en Automatizaciones. <a class="ml-1 font-black text-[#e2c541]" href="#automatizaciones">Activar →</a>
                    @else
                        <strong class="text-white">Sin uso automático:</strong> puedes asignarla a una acción compatible en Automatizaciones. <a class="ml-1 font-black text-[#e2c541]" href="#automatizaciones">Configurar →</a>
                    @endif
                </div>
                @if($template->rejection_reason)<p class="mt-3 text-xs text-rose-200">{{ $template->rejection_reason }}</p>@endif
                <div class="mt-auto flex flex-wrap gap-2 pt-4">
                    @if(in_array($template->status, ['draft', 'rejected'], true))
                        <a class="btn btn-ghost min-h-10 text-xs" href="{{ route('settings.index', ['edit_template' => $template->public_id]) }}#editor-plantilla">Editar mensaje</a>
                    @else
                        <a class="btn btn-ghost min-h-10 text-xs" href="{{ route('settings.index', ['version_template' => $template->public_id]) }}#editor-plantilla">Editar mensaje</a>
                    @endif
                    @if(($account?->provider ?? 'fake') === 'fake')
                        @if($template->status !== 'approved')<form method="POST" action="{{ route('templates.review', $template) }}">@csrf<input type="hidden" name="decision" value="approve"><button class="btn btn-secondary min-h-10 text-xs" type="submit">Aprobar en demo</button></form>@endif
                        @if($template->status !== 'rejected')<form method="POST" action="{{ route('templates.review', $template) }}">@csrf<input type="hidden" name="decision" value="reject"><button class="btn btn-ghost min-h-10 text-xs" type="submit">Marcar rechazada</button></form>@endif
                    @else
                        @if(!$template->meta_id)<form method="POST" action="{{ route('templates.submit', $template) }}">@csrf<button class="btn btn-secondary min-h-10 text-xs" type="submit">Enviar a Meta</button></form>@else<form method="POST" action="{{ route('templates.sync', $template) }}">@csrf<button class="btn btn-secondary min-h-10 text-xs" type="submit">Actualizar estado</button></form>@endif
                    @endif
                </div>
                @if(in_array($template->status, ['approved', 'pending'], true))<p class="mt-3 text-[11px] leading-5 text-[#777d87]">Al editar se crea una nueva versión. Este mensaje seguirá funcionando hasta que apruebes el reemplazo.</p>@endif
            </article>
        @empty
            <div class="empty lg:col-span-2">No hay mensajes en esta categoría.</div>
        @endforelse
        </div>
    @endforeach

    <details id="editor-plantilla" class="mt-5 scroll-mt-32 rounded-2xl border border-white/10 p-4" @if($editingTemplate || $versioningTemplate || $errors->hasAny(['display_name','technical_name','category','body','samples.*','template'])) open @endif>
        <summary class="cursor-pointer font-black">{{ $editingTemplate ? 'Editar mensaje' : ($versioningTemplate ? 'Crear versión editable' : 'Crear una plantilla nueva') }}</summary>
        @if($versioningTemplate)
            <div class="mt-4 rounded-xl border border-sky-300/15 bg-sky-300/5 p-3 text-xs leading-5 text-sky-100/80"><strong>Edición segura:</strong> estás creando una copia de “{{ $versioningTemplate->display_name ?: $versioningTemplate->technical_name }}”. La versión actual no cambia. Cuando apruebes la nueva, sus automatizaciones se actualizarán solas; las campañas ya creadas conservarán su contenido.</div>
        @endif
        <form method="POST" action="{{ $editingTemplate ? route('templates.update', $editingTemplate) : route('templates.store') }}" class="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1fr)_360px]" data-template-editor>
            @csrf @if($editingTemplate) @method('PUT') @endif
            @if($versioningTemplate)<input type="hidden" name="replaces_template" value="{{ $versioningTemplate->public_id }}">@endif
            <div class="space-y-4">
                <div><label class="label" for="display_name">Nombre para el administrador</label><input class="input" id="display_name" name="display_name" value="{{ $templateValue('display_name') }}" placeholder="Ej. Bienvenida a nuevos clientes" required data-template-display><p class="mt-1.5 text-[11px] text-[#777d87]">Este nombre solo se muestra dentro de la plataforma.</p></div>
                <div><label class="label" for="category">¿Para qué se usará?</label><select class="select" id="category" name="category"><option value="utility" @selected($templateValue('category', 'utility') === 'utility')>Mensaje de servicio · responde a una acción del cliente</option><option value="marketing" @selected($templateValue('category') === 'marketing')>Promoción · se envía mediante campañas</option></select></div>
                <div><label class="label" for="body">Mensaje</label><textarea class="textarea min-h-40" id="body" name="body" placeholder="Hola &#123;&#123;1&#125;&#125;. Tu mensaje…" required data-template-body>{{ $templateValue('body') }}</textarea><div class="mt-2 flex flex-wrap items-center gap-2"><button class="btn btn-ghost min-h-9 px-3 text-xs" type="button" data-insert-variable>＋ Insertar dato dinámico</button><span class="text-[11px] text-[#777d87]">Los datos se numeran &#123;&#123;1&#125;&#125;, &#123;&#123;2&#125;&#125;, &#123;&#123;3&#125;&#125;…</span></div></div>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach(range(0, 9) as $i)
                        <div data-template-sample-field><label class="label" for="sample_{{ $i }}">Ejemplo para &#123;&#123;{{ $i + 1 }}&#125;&#125;</label><input class="input" id="sample_{{ $i }}" name="samples[]" value="{{ old("samples.$i", $templateSource?->samples[$i] ?? '') }}" placeholder="Texto de ejemplo"></div>
                    @endforeach
                </div>
                <details class="rounded-xl border border-white/8 p-4"><summary class="cursor-pointer text-sm font-black">Opciones técnicas y formato</summary><div class="mt-4 grid gap-4 sm:grid-cols-2"><div><label class="label" for="technical_name">Nombre para Meta</label><input class="input" id="technical_name" name="technical_name" value="{{ $templateValue('technical_name') }}" placeholder="se_completa_automaticamente" data-template-technical><p class="mt-1.5 text-[11px] text-[#777d87]">Minúsculas, números y guiones bajos. No cambia después de enviarla a Meta.</p></div><div><label class="label" for="language">Idioma</label><input class="input" id="language" name="language" value="{{ $templateValue('language', 'es_PE') }}" required></div><div><label class="label" for="header_type">Encabezado</label><select class="select" id="header_type" name="header_type"><option value="none" @selected($templateValue('header_type', 'none') === 'none')>Sin encabezado</option><option value="text" @selected($templateValue('header_type') === 'text')>Texto</option><option value="image" @selected($templateValue('header_type') === 'image')>Imagen administrada en Meta</option></select></div><div><label class="label" for="header">Texto del encabezado</label><input class="input" id="header" name="header" value="{{ $templateValue('header') }}"></div><div class="sm:col-span-2"><label class="label" for="footer">Pie opcional</label><input class="input" id="footer" name="footer" value="{{ $templateValue('footer') }}" maxlength="60"></div></div></details>
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">@if($editingTemplate || $versioningTemplate)<a class="btn btn-ghost" href="{{ route('settings.index') }}#plantillas">Cancelar edición</a>@endif<button class="btn btn-primary" type="submit">{{ $editingTemplate ? 'Guardar cambios' : ($versioningTemplate ? 'Guardar nueva versión' : 'Guardar borrador') }}</button></div>
            </div>
            <aside class="h-fit rounded-2xl border border-white/8 bg-black/15 p-4 lg:sticky lg:top-24"><p class="eyebrow">Vista previa</p><h3 class="mt-1 font-black">Así lo verá el cliente</h3><div class="mt-4 rounded-[1.6rem] bg-[#0d1712] p-4"><div class="ml-auto min-h-28 whitespace-pre-line rounded-2xl rounded-tr-sm bg-[#1f4f3a] p-3 text-sm leading-6 text-[#edf8f1]" data-template-editor-preview>Escribe el mensaje para ver la vista previa.</div></div><p class="mt-3 text-xs leading-5 text-[#858b95]">Los ejemplos solo ayudan a Meta y a esta vista previa; al enviar, la plataforma los reemplaza con datos reales de cada acción.</p></aside>
        </form>
    </details>
</section>
