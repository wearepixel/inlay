<!DOCTYPE html>
<html>
<head>
    <title>{{ $page->effectiveMetaTitle() }}</title>
    @if ($page->effectiveMetaDescription())
        <meta name="description" content="{{ $page->effectiveMetaDescription() }}">
    @endif
</head>
<body>
    {!! $composition !!}
</body>
</html>
