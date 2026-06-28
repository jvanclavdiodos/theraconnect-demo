@extends('layouts.portal')

@section('title', $assessment->title() . ' — ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('portal.assessments.index') }}">Questionnaires</a></li>
    <li class="breadcrumb-item active">{{ $assessment->title() }}</li>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header">
                <strong>{{ $definition['title'] }}</strong> — {{ $definition['name'] }}
            </div>
            <div class="card-body">
                @if($assessment->status === 'completed')
                    <div class="text-center py-3">
                        <i class="bi bi-check-circle-fill text-success fs-1"></i>
                        <h4 class="mt-2 mb-1">Completed</h4>
                        <p class="text-muted mb-3">
                            {{ $assessment->completed_at?->format('M j, Y') }} ·
                            Score <strong>{{ $assessment->score }}</strong> / {{ $definition['max'] }}
                            ({{ $assessment->severity() }})
                        </p>
                        <a href="{{ route('portal.assessments.index') }}" class="btn btn-outline-secondary btn-sm">Back to questionnaires</a>
                    </div>
                @else
                    <p class="text-muted">{{ $definition['prompt'] }}</p>

                    @if($errors->any())
                        <div class="alert alert-danger">Please answer every question before submitting.</div>
                    @endif

                    <form method="POST" action="{{ route('portal.assessments.submit', $assessment) }}">
                        @csrf
                        @foreach($definition['items'] as $i => $item)
                            <fieldset class="mb-4 pb-2 border-bottom">
                                <legend class="fs-6 fw-semibold">{{ $i + 1 }}. {{ $item }}</legend>
                                <div class="d-flex flex-column flex-sm-row flex-wrap gap-2 gap-sm-3">
                                    @foreach($options as $value => $label)
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio"
                                                   name="responses[{{ $i }}]" id="q{{ $i }}-{{ $value }}"
                                                   value="{{ $value }}" {{ (string) old("responses.$i") === (string) $value ? 'checked' : '' }} required>
                                            <label class="form-check-label" for="q{{ $i }}-{{ $value }}">{{ $value }} — {{ $label }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endforeach

                        <button type="submit" class="btn btn-primary">Submit questionnaire</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
