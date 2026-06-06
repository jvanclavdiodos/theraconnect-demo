@extends('layouts.app')

@section('title', 'Edit Intent — ' . $intent->display_name . ' — ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('chatbot-content.index') }}">Chatbot Content</a></li>
    <li class="breadcrumb-item active">Edit: {{ $intent->display_name }}</li>
@endsection

@section('content')
<h2>Edit Intent: {{ $intent->display_name }}</h2>

<div class="card shadow-sm mt-3" x-data="intentForm(
    @json($intent->training_phrases),
    @json($intent->responses->map(fn($r) => ['response_text' => $r->response_text, 'priority' => $r->priority, 'is_fallback' => (int) $r->is_fallback])->toArray()),
    {{ $intent->is_active ? 'true' : 'false' }}
)">
    <div class="card-body">
        <form action="{{ route('chatbot-content.update', $intent) }}" method="POST">
            @csrf @method('PUT')

            <div class="row g-3">
                <div class="col-md-4">
                    <label for="intent_key" class="form-label">Intent Key</label>
                    <input type="text" id="intent_key" name="intent_key" class="form-control" value="{{ old('intent_key', $intent->intent_key) }}" required>
                </div>
                <div class="col-md-4">
                    <label for="display_name" class="form-label">Display Name</label>
                    <input type="text" id="display_name" name="display_name" class="form-control" value="{{ old('display_name', $intent->display_name) }}" required>
                </div>
                <div class="col-md-4">
                    <label for="category" class="form-label">Category</label>
                    <select id="category" name="category" class="form-select" required>
                        <option value="faq" {{ old('category', $intent->category) === 'faq' ? 'selected' : '' }}>FAQ</option>
                        <option value="scheduling" {{ old('category', $intent->category) === 'scheduling' ? 'selected' : '' }}>Scheduling</option>
                        <option value="smalltalk" {{ old('category', $intent->category) === 'smalltalk' ? 'selected' : '' }}>Small Talk</option>
                        <option value="fallback" {{ old('category', $intent->category) === 'fallback' ? 'selected' : '' }}>Fallback</option>
                    </select>
                </div>
            </div>

            <div class="mt-3">
                <div class="form-check form-switch">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" id="is_active" name="is_active" value="1" class="form-check-input" {{ old('is_active', $intent->is_active) ? 'checked' : '' }}>
                    <label for="is_active" class="form-check-label">Active</label>
                </div>
            </div>

            <div class="mt-4">
                <label class="form-label">Training Phrases</label>
                <template x-for="(phrase, i) in phrases" :key="i">
                    <div class="input-group mb-2">
                        <input type="text" :name="'training_phrases[]'" class="form-control" x-model="phrases[i]" placeholder="Enter a training phrase..." required>
                        <button type="button" class="btn btn-outline-danger" @click="phrases.splice(i, 1)" x-show="phrases.length > 1">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </template>
                <button type="button" class="btn btn-sm btn-outline-secondary" @click="phrases.push('')">
                    <i class="bi bi-plus"></i> Add Phrase
                </button>
            </div>

            <div class="mt-4">
                <label class="form-label">Responses</label>
                <template x-for="(resp, i) in responses" :key="i">
                    <div class="card mb-2 border-secondary">
                        <div class="card-body py-2">
                            <div class="row g-2">
                                <div class="col-md-8">
                                    <textarea :name="'responses[' + i + '][response_text]'" class="form-control" rows="2" x-model="responses[i].response_text" placeholder="Bot's reply text..." required></textarea>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small">Priority</label>
                                    <input type="number" :name="'responses[' + i + '][priority]'" class="form-control" x-model="responses[i].priority" min="0">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small">Fallback?</label>
                                    <select :name="'responses[' + i + '][is_fallback]'" class="form-select" x-model="responses[i].is_fallback">
                                        <option value="0">No</option>
                                        <option value="1">Yes</option>
                                    </select>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger mt-2" @click="responses.splice(i, 1)" x-show="responses.length > 1">
                                Remove Response
                            </button>
                        </div>
                    </div>
                </template>
                <button type="button" class="btn btn-sm btn-outline-secondary" @click="responses.push({response_text: '', priority: 0, is_fallback: 0})">
                    <i class="bi bi-plus"></i> Add Response
                </button>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="{{ route('chatbot-content.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function intentForm(phrases, responses, active) {
    return {
        phrases: phrases || [''],
        responses: responses || [{response_text: '', priority: 0, is_fallback: 0}],
        active: active || false,
    }
}
</script>
@endpush
@endsection
