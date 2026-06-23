@extends('layouts.portal')

@section('title', 'Book Appointment — ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('portal.appointments.index') }}">Appointments</a></li>
    <li class="breadcrumb-item active">Book</li>
@endsection

@section('content')
<h1 class="tc-page-title mb-1">Book an Appointment</h1>
<p class="tc-page-sub">Choose a clinician, pick a date, then select an open time.</p>

<div class="row g-4">
    {{-- Step 1: clinician --}}
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><strong>1. Clinician</strong></div>
            <div class="list-group list-group-flush">
                @forelse($clinicians as $c)
                    <a href="{{ route('portal.appointments.book', ['clinician_id' => $c->id]) }}"
                       class="list-group-item list-group-item-action d-flex align-items-center gap-2 {{ $selectedClinician && $selectedClinician->id === $c->id ? 'active' : '' }}">
                        <i class="bi bi-person-circle"></i>
                        <span>
                            <span class="fw-semibold">{{ $c->user->name }}</span>
                            @if($c->specialization)<br><small class="{{ $selectedClinician && $selectedClinician->id === $c->id ? 'text-white-50' : 'text-muted' }}">{{ $c->specialization }}</small>@endif
                        </span>
                    </a>
                @empty
                    <div class="list-group-item text-muted">No clinicians available right now.</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Step 2: date --}}
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><strong>2. Date</strong></div>
            <div class="card-body">
                @if($selectedClinician)
                    <form method="GET" action="{{ route('portal.appointments.book') }}">
                        <input type="hidden" name="clinician_id" value="{{ $selectedClinician->id }}">
                        <label for="date" class="form-label">Pick a date</label>
                        <input type="date" id="date" name="date" class="form-control"
                               min="{{ now()->format('Y-m-d') }}" value="{{ $date }}"
                               onchange="this.form.submit()">
                        <noscript><button class="btn btn-primary mt-2 w-100">Show times</button></noscript>
                    </form>
                @else
                    <p class="text-muted mb-0">Select a clinician first.</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Step 3: slot + confirm --}}
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><strong>3. Time &amp; confirm</strong></div>
            <div class="card-body">
                @if(! $selectedClinician || ! $date)
                    <p class="text-muted mb-0">Choose a clinician and date to see open times.</p>
                @else
                    @php $open = collect($slots)->where('available', true)->values(); @endphp
                    @if($open->isEmpty())
                        <p class="text-muted mb-0">No open times for {{ \Carbon\Carbon::parse($date)->format('M j, Y') }}. Try another date.</p>
                    @else
                        <form method="POST" action="{{ route('portal.appointments.store') }}">
                            @csrf
                            <input type="hidden" name="clinician_id" value="{{ $selectedClinician->id }}">

                            @error('requested_at')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

                            <label class="form-label">Available times</label>
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                @foreach($open as $slot)
                                    @php $start = explode('-', $slot['slot'])[0]; @endphp
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="requested_at"
                                               id="slot-{{ $start }}" value="{{ $date }} {{ $start }}:00" required>
                                        <label class="form-check-label" for="slot-{{ $start }}">{{ $slot['slot'] }}</label>
                                    </div>
                                @endforeach
                            </div>

                            <label class="form-label">Mode</label>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="mode" id="mode-in" value="in_person" checked>
                                    <label class="form-check-label" for="mode-in">In person</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="mode" id="mode-online" value="online">
                                    <label class="form-check-label" for="mode-online">Online (video)</label>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="reason" class="form-label">Reason <span class="text-muted">(optional)</span></label>
                                <textarea name="reason" id="reason" rows="2" class="form-control" maxlength="500">{{ old('reason') }}</textarea>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Confirm booking</button>
                        </form>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
