<!DOCTYPE html>
<html>
<head>
    <x-filament-block-editor::styles />
</head>
<body data-test-page="{{ $page->path }}">
    {!! $header !!}
    <h1>{{ $page->title }}</h1>
    <main>{!! $content !!}</main>
    {!! $footer !!}
</body>
</html>
