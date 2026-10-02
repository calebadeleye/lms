@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ rtrim((string) config('services.frontend.url'), '/') }}/branding/logo.png" class="logo" height="53" alt="{{ config('app.name') }}">
</a>
</td>
</tr>
