@props(['name' => 'box'])
<svg {{ $attributes->merge(['class' => 'icon']) }} width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
@switch($name)
@case('grid')<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>@break
@case('users')<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m20 0v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/><circle cx="9" cy="7" r="4"/>@break
@case('badge')<rect x="4" y="5" width="16" height="16" rx="2"/><path d="M9 5V3h6v2M8 17c0-3 8-3 8 0"/><circle cx="12" cy="11" r="2"/>@break
@case('plus-circle')<circle cx="12" cy="12" r="9"/><path d="M12 8v8m-4-4h8"/>@break
@case('history')<path d="M3 11a9 9 0 1 1 2.7 7M3 4v7h7m2-4v5l3 2"/>@break
@case('store')<path d="M3 10l2-7h14l2 7M4 13v8h16v-8M9 21v-7h6v7M3 10a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0"/>@break
@case('shield')<path d="M12 3l8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3zM8 12l3 3 5-6"/>@break
@case('logout')<path d="M9 21H4V3h5m6 5 4 4-4 4m-7-4h11"/>@break
@case('plus')<path d="M12 5v14M5 12h14"/>@break
@case('arrow')<path d="M5 12h14m-6-6 6 6-6 6"/>@break
@case('back')<path d="M19 12H5m6-6-6 6 6 6"/>@break
@case('edit')<path d="m15 5 4 4M4 20l5-1L21 7a2.8 2.8 0 0 0-4-4L5 15l-1 5z"/>@break
@case('trash')<path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7m4-7v7"/>@break
@case('alert')<path d="m12 3 10 18H2L12 3zm0 6v5m0 3h.01"/>@break
@case('check')<path d="m5 12 4 4L19 6"/>@break
@case('image')<rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8" cy="8" r="1"/><path d="m3 17 6-6 4 4 3-3 5 5"/>@break
@default<path d="m12 3 9 5v8l-9 5-9-5V8l9-5zm0 10v8M3 8l9 5 9-5M8 5l9 5"/>
@endswitch
</svg>
