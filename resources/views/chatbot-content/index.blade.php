@extends('layouts.app')

@section('title', 'Chatbot Content — ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item active">Chatbot Content</li>
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Chatbot Intents</h2>
    <a href="{{ route('chatbot-content.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> New Intent
    </a>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Intent Key</th>
                    <th>Display Name</th>
                    <th>Category</th>
                    <th>Phrases</th>
                    <th>Responses</th>
                    <th>Active</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($intents as $intent)
                    <tr>
                        <td><code>{{ $intent->intent_key }}</code></td>
                        <td>{{ $intent->display_name }}</td>
                        <td>
                            <span class="badge bg-secondary">{{ ucfirst($intent->category) }}</span>
                        </td>
                        <td>{{ count($intent->training_phrases) }}</td>
                        <td>{{ $intent->responses->count() }}</td>
                        <td>
                            @if ($intent->is_active)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-danger">Inactive</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('chatbot-content.edit', $intent) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="{{ route('chatbot-content.destroy', $intent) }}" method="POST" class="d-inline"
                                  x-data @submit.prevent="if (confirm('Delete this intent?')) $el.submit()">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <i class="bi bi-robot text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-2 mb-3">No intents created yet.</p>
                            <a href="{{ route('chatbot-content.create') }}" class="btn btn-primary btn-sm">
                                <i class="bi bi-plus-lg"></i> Create Your First Intent
                            </a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">
    {{ $intents->links() }}
</div>
@endsection
