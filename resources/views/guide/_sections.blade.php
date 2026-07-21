<div class="row g-3">
@foreach($sections as $section)
<div class="col-12 col-lg-6"><article class="card h-100"><div class="card-body">
<h2 class="h5">{{ $section['title'] }}</h2><p class="text-body-secondary">{{ $section['description'] }}</p>
@if(isset($actionRoutes[$section['action']]))<a class="btn btn-sm btn-outline-primary" href="{{ $actionRoutes[$section['action']] }}">Open section <i class="bi bi-arrow-right"></i></a>@endif
</div></article></div>
@endforeach
</div>
