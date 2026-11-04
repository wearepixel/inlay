<x-mcp::app title="Inlay preview">
    <x-slot:head>
        <style>
            :root { color-scheme: light dark; --border: #d4d4d8; --muted: #71717a; --accent: #2563eb; }
            body { margin: 0; font: 13px/1.4 system-ui, sans-serif; }
            .bar { display: flex; gap: 8px; align-items: center; padding: 8px; border-bottom: 1px solid var(--border); flex-wrap: wrap; }
            .bar button { font: inherit; padding: 4px 10px; border: 1px solid var(--border); border-radius: 6px; background: transparent; color: inherit; cursor: pointer; }
            .bar button[aria-pressed="true"] { border-color: var(--accent); color: var(--accent); }
            .bar .status { margin-left: auto; color: var(--muted); }
            .stage { display: flex; justify-content: center; background: color-mix(in srgb, var(--border) 25%, transparent); overflow: auto; }
            iframe { border: 0; background: #fff; height: 720px; transition: width .2s ease; max-width: 100%; }
            .warnings { margin: 0; padding: 8px 8px 8px 28px; color: #b45309; }
            .empty { padding: 24px; color: var(--muted); text-align: center; }
        </style>
        <script type="module">
            createMcpApp(async (app) => {
                const frame = document.getElementById('frame');
                const status = document.getElementById('status');
                const warnings = document.getElementById('warnings');
                const buttons = document.querySelectorAll('[data-width]');
                let previewUrl = null;

                buttons.forEach((button) => button.addEventListener('click', () => {
                    buttons.forEach((other) => other.setAttribute('aria-pressed', String(other === button)));
                    frame.style.width = button.dataset.width + 'px';
                }));

                document.getElementById('open').addEventListener('click', () => previewUrl && app.openLink(previewUrl));

                app.onToolResult((result) => {
                    const data = result?.structuredContent ?? {};
                    previewUrl = data.preview_url ?? null;

                    if (! previewUrl) {
                        const message = document.createElement('p');
                        message.className = 'empty';
                        message.textContent = result?.isError
                            ? 'The preview could not be produced - see the tool result for the problems.'
                            : 'Preview links are not enabled on this site.';
                        document.getElementById('stage').replaceChildren(message);
                        return;
                    }

                    frame.src = previewUrl;
                    status.textContent = (data.page?.title ?? 'Page') + ' - ' + (data.previewing ?? '');
                    warnings.replaceChildren(...(data.warnings ?? []).map((text) => {
                        const item = document.createElement('li');
                        item.textContent = text;
                        return item;
                    }));
                });
            });
        </script>
    </x-slot:head>

    <div class="bar">
        <button type="button" data-width="1440" aria-pressed="true">Desktop</button>
        <button type="button" data-width="390" aria-pressed="false">Mobile</button>
        <button type="button" id="open">Open in browser</button>
        <span class="status" id="status">Waiting for preview...</span>
    </div>
    <ul class="warnings" id="warnings"></ul>
    <div class="stage" id="stage">
        <iframe id="frame" title="Page preview" style="width: 1440px"></iframe>
    </div>
</x-mcp::app>
