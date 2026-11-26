@if(session('success'))<div class="flash success" role="status"><x-icon name="check" />{{ session('success') }}</div>@endif
@if($errors->any())<div class="flash error" role="alert"><x-icon name="alert" /><div><strong>Please check your information.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif
