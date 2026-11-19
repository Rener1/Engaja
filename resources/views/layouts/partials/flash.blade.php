@php($containerClass = $containerClass ?? 'container')

@if(session('success'))
<div class="{{ $containerClass }}">
  <div class="row justify-content-center mt-2">
    <div class="col-md-8 col-lg-6">
        <div class="alert alert-success alert-dismissible fade show text-center" role="alert">
          {{ session('success') }}
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    </div>
  </div>
</div>
@endif

@if (session('error'))
  <div class="{{ $containerClass }}">
    <div class="alert alert-danger text-center mt-2">{{ session('error') }}</div>
  </div>
@endif
