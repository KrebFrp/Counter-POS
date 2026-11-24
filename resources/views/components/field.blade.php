@props(['name', 'label', 'type' => 'text', 'value' => '', 'hint' => null])
<div class="field">
    <label for="{{ $name }}">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" @if(! in_array($type, ['file', 'password'])) value="{{ old($name, $value) }}" @endif {{ $attributes->class(['invalid' => $errors->has($name)]) }} @if($hint || $errors->has($name)) aria-describedby="{{ $name }}-help" @endif @if($errors->has($name)) aria-invalid="true" @endif>
    <div id="{{ $name }}-help">@error($name)<p class="field-error">{{ $message }}</p>@else @if($hint)<p class="field-hint">{{ $hint }}</p>@endif @enderror</div>
</div>
