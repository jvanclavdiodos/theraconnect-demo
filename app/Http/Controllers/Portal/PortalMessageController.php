<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\ActivityLogService;
use App\Services\MessageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PortalMessageController extends Controller
{
    public function __construct(private MessageService $messages) {}

    /**
     * The patient has a single thread — with their assigned clinician. Open (or
     * create) it and show the messages. Patients without an assigned clinician
     * see a friendly prompt instead.
     */
    public function index(Request $request): View
    {
        $patient = $request->user()->patient;
        abort_unless($patient !== null, 404);

        if (! $patient->assigned_clinician_id) {
            return view('portal.messages.index', ['conversation' => null]);
        }

        $patient->loadMissing('assignedClinician.user');
        $conversation = $this->messages
            ->conversationFor($patient, $patient->assignedClinician)
            ->load(['clinician.user', 'patient.user', 'messages.sender']);

        $this->messages->markRead($conversation, $request->user());

        return view('portal.messages.index', compact('conversation'));
    }

    public function send(Request $request, Conversation $conversation): RedirectResponse
    {
        Gate::authorize('participate', $conversation);

        $validated = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $message = $this->messages->send($conversation, $request->user(), $validated['body']);

        app(ActivityLogService::class)->log($request->user(), 'message.sent', $message);

        return redirect()->route('portal.messages.index');
    }
}
