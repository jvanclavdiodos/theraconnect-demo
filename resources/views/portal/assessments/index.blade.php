@extends('layouts.portal')

@section('title', 'Questionnaires — ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item active">Questionnaires</li>
@endsection

@section('content')
<div class="mb-4">
    <h1 class="tc-page-title">Questionnaires</h1>
    <p class="tc-page-sub mb-0">Standardized check-ins your clinician uses to track progress.</p>
</div>

<div class="card">
    <div class="list-group list-group-flush">
        @forelse($assessments as $assessment)
            @php $definition = \App\Support\Assessments::definition($assessment->instrument); @endphp
            <a href="{{ route('portal.assessments.show', $assessment) }}"
               class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                <div>
                    <div class="fw-semibold">{{ $assessment->title() }}</div>
                    <div class="text-muted small">
                        <span class="d-block mb-1">{{ $definition['purpose'] ?? '' }}</span>
                        @if($assessment->status === 'completed')
                            Completed {{ $assessment->completed_at?->format('M j, Y') }} · Score {{ $assessment->score }} ({{ $assessment->severity() }})
                        @else
                            Assigned {{ $assessment->created_at->format('M j, Y') }}
                        @endif
                    </div>
                </div>
                @if($assessment->status === 'pending')
                    <span class="badge bg-warning">To complete</span>
                @else
                    <span class="badge bg-info">Done</span>
                @endif
            </a>
        @empty
            <div class="list-group-item">
                <div class="tc-empty">
                    <div class="tc-empty-icon"><i class="bi bi-card-checklist"></i></div>
                    <div>No questionnaires assigned yet.</div>
                </div>
            </div>
        @endforelse
    </div>
</div>
@endsection
