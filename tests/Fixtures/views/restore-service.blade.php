<h2>{{ $values['title'] }}</h2>
<p>{{ $values['details'] }}</p>
@if (isset($values['owner_text']))
    <p>{{ $values['owner_text'] }}</p>
@endif
