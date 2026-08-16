<div class="tc-guide-list">
    @foreach($sections as $index => $section)
        <article class="card tc-guide-card">
            <div class="card-header tc-guide-header">
                <span class="tc-guide-number" aria-hidden="true">{{ $index + 1 }}</span>
                <div class="tc-guide-heading">
                    <h2 class="h5 mb-1">{{ $section['title'] }}</h2>
                    <p class="text-body-secondary fw-normal mb-0">{{ $section['description'] }}</p>
                </div>
            </div>
            <div class="card-body tc-guide-body">
                @if(filled($section['before_you_start'] ?? null))
                    <div class="alert alert-info tc-guide-notice mb-3">
                        <strong><i class="bi bi-info-circle me-1" aria-hidden="true"></i> Before you start:</strong>
                        {{ $section['before_you_start'] }}
                    </div>
                @endif

                <h3 class="h6 mb-3">Steps</h3>
                <ol class="tc-guide-steps mb-4">
                    @foreach($section['steps'] ?? [] as $step)
                        <li>{{ $step }}</li>
                    @endforeach
                </ol>

                <div class="tc-guide-result mb-3">
                    <h3 class="h6 mb-1">Expected result</h3>
                    <p class="mb-0">{{ $section['expected_result'] }}</p>
                </div>

                @if(!empty($section['tips']))
                    <h3 class="h6 mb-2">Important reminders</h3>
                    <ul class="tc-guide-reminders text-body-secondary mb-4">
                        @foreach($section['tips'] as $tip)
                            <li>{{ $tip }}</li>
                        @endforeach
                    </ul>
                @endif

                @if(isset($actionRoutes[$section['action']]))
                    <a class="btn btn-outline-primary tc-guide-action" href="{{ $actionRoutes[$section['action']] }}">
                        Open {{ $section['title'] }} <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    </a>
                @endif
            </div>
        </article>
    @endforeach
</div>
