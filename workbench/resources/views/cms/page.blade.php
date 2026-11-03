<!DOCTYPE html>
<html>
<head>
    <x-filament-block-editor::styles />
</head>
<body data-test-page="{{ $page->path }}">
    <h1>{{ $page->title }}</h1>
    <main>{!! $content !!}</main>
</body>
</html>
