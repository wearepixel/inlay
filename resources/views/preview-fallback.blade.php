<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('inlay::admin.preview.fallback_title') }}</title>
</head>
<body style="margin: 0; font-family: -apple-system, BlinkMacSystemFont, sans-serif;">
    <div style="background: #fef3c7; color: #92400e; padding: 8px 16px; font-size: 12px; border-bottom: 1px solid #fde68a;">
        {!! __('inlay::admin.preview.fallback_notice', ['method' => '<code>Inlay::previewView()</code>']) !!}
    </div>
    <div>
        {!! $composition !!}
    </div>
</body>
</html>
