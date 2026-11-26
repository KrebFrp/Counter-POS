@props(['path' => null, 'name' => '', 'avatar' => false, 'large' => false])
@if($path)
<img src="{{ Storage::disk('public')->url($path) }}" alt="{{ $name }}" @class(['record-image', 'avatar' => $avatar, 'large-image' => $large]) loading="lazy">
@else
<span @class(['record-image', 'image-placeholder', 'avatar' => $avatar, 'large-image' => $large]) role="img" aria-label="{{ $name }} — no image">@if($avatar){{ mb_strtoupper(mb_substr($name ?: '?', 0, 1)) }}@else<x-icon name="box" />@endif</span>
@endif
