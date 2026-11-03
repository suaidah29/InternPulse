@if(session('success'))<div class="notice success" role="status">{{ session('success') }}</div>@endif
@if(session('warning'))<div class="notice warning" role="status">{{ session('warning') }}</div>@endif
@if($errors->any())<div class="notice warning" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
