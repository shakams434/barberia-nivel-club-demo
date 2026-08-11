@php
    $templateValue = fn ($field, $default = '') => old($field, $editingTemplate?->{$field} ?? $default);
@endphp
<section id="plantillas" class="card scroll-mt-32">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div><p class="eyebrow">Contenido de WhatsApp</p><h2 class="mt-1 text-xl font-black">Plantillas de mensajes</h2><p class="subtitle max-w-3xl">Una plantilla define qué verá el cliente. Después de aprobarla, podrás usarla en una campaña o asignarla a una acción automática.</p></div>
        <a class="btn btn-secondary" href="#editor-plantilla">＋ Nueva plantilla</a>
    </div>

    <div class="mt-5 grid gap-3 sm:grid-cols-3">
        <div class="rounded-xl border border-white/8 p-3"><span class="badge badge-neutral">1</span><strong class="ml-2 text-sm">Escribe y prueba</strong><p class="mt-2 text-xs leading-5 text-[#858b95]">Usa ejemplos para comprobar cómo se verá.</p></div>
        <div class="rounded-xl border border-white/8 p-3"><span class="badge badge-neutral">2</span><strong class="ml-2 text-sm">Aprueba en Meta</strong><p class="mt-2 text-xs leading-5 text-[#858b95]">Meta valida el contenido y su categoría.</p></div>
        <div class="rounded-xl border border-white/8 p-3"><span class="badge badge-neutral">3</span><strong class="ml-2 text-sm">Elige dónde se usa</strong><p class="mt-2 text-xs leading-5 text-[#858b95]">Campaña manual o automatización por acción.</p></div>
    </div>

    <div class="mt-5 grid gap-3 lg:grid-cols-2">
        @forelse($templates as $template)
            @php
                $renderedBody = $template->body;
                foreach(array_values($template->samples ?? []) as $index => $sample) $renderedBody = str_replace('{{'.($index + 1).'}}', $sample, $renderedBody);
                $activeUses = $template->automations->where('active', true)->pluck('event_key');
            @endphp
            <article class="card-soft flex flex-col">
                <div class="flex flex-wrap items-start justify-between gap-2"><div class="min-w-0"><h3 class="font-black">{{ $template->display_name ?: $template->technical_name }}</h3><p class="mt-0.5 truncate text-[11px] text-[#737984]">Meta: {{ $template->technical_name }} · {{ $template->language }}</p></div><span class="badge {{ $template->status === 'approved' ? 'badge-success' : ($template->status === 'rejected' ? 'badge-danger' : 'badge-warning') }}">{{ $templateStatusLabels[$template->status] ?? $template->status }}</span></div>
                <div class="mt-3 rounded-2xl bg-[#0d1712] p-3"><p class="whitespace-pre-line text-xs leading-5 text-[#d7e7dc]">{{ $renderedBody }}</p></div>
                <div class="mt-3 flex flex-wrap gap-1.5"><span class="badge badge-neutral">{{ $template->category === 'marketing' ? 'Campañas promocionales' : 'Mensajes de servicio' }}</span>@foreach($activeUses as $eventKey)<span class="badge badge-success">Automática: {{ $automationDefinitions[$eventKey]['name'] ?? $eventKey }}</span>@endforeach</div>
                @if($template->rejection_reason)<p class="mt-3 text-xs text-rose-200">{{ $template->rejection_reason }}</p>@endif
                <div class="mt-auto flex flex-wrap gap-2 pt-4">
                    @if(in_array($template->status, ['draft', 'rejected'], true))<a class="btn btn-ghost min-h-10 text-xs" href="{{ route('settings.index', ['edit_template' => $template->public_id]) }}#editor-plantilla">Editar borrador</a>@endif
                    @if(($account?->provider ?? 'fake') === 'fake')
                        <form method="POST" action="{{ route('templates.review', $template) }}">@csrf<input type="hidden" name="decision" value="approve"><button class="btn btn-secondary min-h-10 text-xs" type="submit">Aprobar en demo</button></form>
                        <form method="POST" action="{{ route('templates.review', $template) }}">@csrf<input type="hidden" name="decision" value="reject"><button class="btn btn-ghost min-h-10 text-xs" type="submit">Marcar rechazada</button></form>
                    @else
                        @if(!$template->meta_id)<form method="POST" action="{{ route('templates.submit', $template) }}">@csrf<button class="btn btn-secondary min-h-10 text-xs" type="submit">Enviar a Meta</button></form>@else<form method="POST" action="{{ route('templates.sync', $template) }}">@csrf<button class="btn btn-secondary min-h-10 text-xs" type="submit">Actualizar estado</button></form>@endif
                    @endif
                </div>
                @if(in_array($template->status, ['approved', 'pending'], true))<p class="mt-3 text-[11px] leading-5 text-[#777d87]">El contenido aprobado no se edita directamente. Crea otra versión y cambia su uso cuando esté aprobada.</p>@endif
            </article>
        @empty
            <div class="empty lg:col-span-2">Crea la primera plantilla para comenzar.</div>
        @endforelse
    </div>

    <details id="editor-plantilla" class="mt-5 scroll-mt-32 rounded-2xl border border-white/10 p-4" @if($editingTemplate || $errors->hasAny(['display_name','technical_name','category','body','samples.*','template'])) open @endif>
        <summary class="cursor-pointer font-black">{{ $editingTemplate ? 'Editar borrador' : 'Crear una plantilla nueva' }}</summary>
        <form method="POST" action="{{ $editingTemplate ? route('templates.update', $editingTemplate) : route('templates.store') }}" class="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1fr)_360px]" data-template-editor>
            @csrf @if($editingTemplate) @method('PUT') @endif
            <div class="space-y-4">
                <div><label class="label" for="display_name">Nombre para el administrador</label><input class="input" id="display_name" name="display_name" value="{{ $templateValue('display_name') }}" placeholder="Ej. Bienvenida a nuevos clientes" required data-template-display><p class="mt-1.5 text-[11px] text-[#777d87]">Este nombre solo se muestra dentro de la plataforma.</p></div>
                <div><label class="label" for="category">¿Para qué se usará?</label><select class="select" id="category" name="category"><option value="utility" @selected($templateValue('category', 'utility') === 'utility')>Mensaje de servicio · responde a una acción del cliente</option><option value="marketing" @selected($templateValue('category') === 'marketing')>Promoción · se envía mediante campañas</option></select></div>
                <div><label class="label" for="body">Mensaje</label><textarea class="textarea min-h-40" id="body" name="body" placeholder="Hola &#123;&#123;1&#125;&#125;. Tu mensaje…" required data-template-body>{{ $templateValue('body') }}</textarea><div class="mt-2 flex flex-wrap items-center gap-2"><button class="btn btn-ghost min-h-9 px-3 text-xs" type="button" data-insert-variable>＋ Insertar dato dinámico</button><span class="text-[11px] text-[#777d87]">Los datos se numeran &#123;&#123;1&#125;&#125;, &#123;&#123;2&#125;&#125;, &#123;&#123;3&#125;&#125;…</span></div></div>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach(range(0, 9) as $i)
                        <div data-template-sample-field><label class="label" for="sample_{{ $i }}">Ejemplo para &#123;&#123;{{ $i + 1 }}&#125;&#125;</label><input class="input" id="sample_{{ $i }}" name="samples[]" value="{{ old("samples.$i", $editingTemplate?->samples[$i] ?? '') }}" placeholder="Texto de ejemplo"></div>
                    @endforeach
                </div>
                <details class="rounded-xl border border-white/8 p-4"><summary class="cursor-pointer text-sm font-black">Opciones técnicas y formato</summary><div class="mt-4 grid gap-4 sm:grid-cols-2"><div><label class="label" for="technical_name">Nombre para Meta</label><input class="input" id="technical_name" name="technical_name" value="{{ $templateValue('technical_name') }}" placeholder="se_completa_automaticamente" data-template-technical><p class="mt-1.5 text-[11px] text-[#777d87]">Minúsculas, números y guiones bajos. No cambia después de enviarla a Meta.</p></div><div><label class="label" for="language">Idioma</label><input class="input" id="language" name="language" value="{{ $templateValue('language', 'es_PE') }}" required></div><div><label class="label" for="header_type">Encabezado</label><select class="select" id="header_type" name="header_type"><option value="none" @selected($templateValue('header_type', 'none') === 'none')>Sin encabezado</option><option value="text" @selected($templateValue('header_type') === 'text')>Texto</option><option value="image" @selected($templateValue('header_type') === 'image')>Imagen administrada en Meta</option></select></div><div><label class="label" for="header">Texto del encabezado</label><input class="input" id="header" name="header" value="{{ $templateValue('header') }}"></div><div class="sm:col-span-2"><label class="label" for="footer">Pie opcional</label><input class="input" id="footer" name="footer" value="{{ $templateValue('footer') }}" maxlength="60"></div></div></details>
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">@if($editingTemplate)<a class="btn btn-ghost" href="{{ route('settings.index') }}#plantillas">Cancelar edición</a>@endif<button class="btn btn-primary" type="submit">{{ $editingTemplate ? 'Guardar cambios' : 'Guardar borrador' }}</button></div>
            </div>
            <aside class="h-fit rounded-2xl border border-white/8 bg-black/15 p-4 lg:sticky lg:top-24"><p class="eyebrow">Vista previa</p><h3 class="mt-1 font-black">Así lo verá el cliente</h3><div class="mt-4 rounded-[1.6rem] bg-[#0d1712] p-4"><div class="ml-auto min-h-28 whitespace-pre-line rounded-2xl rounded-tr-sm bg-[#1f4f3a] p-3 text-sm leading-6 text-[#edf8f1]" data-template-editor-preview>Escribe el mensaje para ver la vista previa.</div></div><p class="mt-3 text-xs leading-5 text-[#858b95]">Los ejemplos solo ayudan a Meta y a esta vista previa; al enviar, la plataforma los reemplaza con datos reales de cada acción.</p></aside>
        </form>
    </details>
</section>
