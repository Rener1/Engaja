@if($errors->any())
  <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul></div>
@endif
