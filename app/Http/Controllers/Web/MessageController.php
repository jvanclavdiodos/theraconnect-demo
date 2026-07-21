<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Clinician;
use App\Models\Conversation;
use App\Models\Patient;
use App\Services\ActivityLogService;
use App\Services\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class MessageController extends Controller
{
    public function __construct(private MessageService $messages) {}

    /** Inbox: this clinician's conversations + caseload patients to compose to. */
    public function index(Request $request): View
    {
        $clinician = $this->currentClinician($request);

        $conversations = Conversation::where('clinician_id', $clinician->id)
            ->whereHas('patient', fn ($query) => $query->assignedTo($clinician))
            ->with(['patient.user', 'latestMessage'])
            ->orderByDesc('last_message_at')
            ->get();

        $caseload = Patient::assignedTo($clinician)
            ->with('user')
            ->get();

        return view('messages.index', compact('conversations', 'caseload'));
    }

    /** Open (or create) the thread with a caseload patient. */
    public function open(Request $request): RedirectResponse
    {
        $clinician = $this->currentClinician($request);

        $validated = $request->validate(['patient_id' => ['required', 'integer']]);
        $patient = Patient::findOrFail($validated['patient_id']);

        abort_unless($patient->isAssignedTo($clinician), 403);

        $conversation = $this->messages->conversationFor($patient, $clinician);

        return redirect()->route('messages.show', $conversation);
    }

    public function show(Request $request, Conversation $conversation): View
    {
        Gate::authorize('participate', $conversation);

        app(ActivityLogService::class)->log($request->user(), 'message.thread_opened', $conversation);

        $conversation->load(['patient.user', 'clinician.user', 'messages.sender']);
        $this->messages->markRead($conversation, $request->user());

        $clinician = $this->currentClinician($request);
        $conversations = Conversation::where('clinician_id', $clinician->id)
            ->whereHas('patient', fn ($query) => $query->assignedTo($clinician))
            ->with(['patient.user', 'latestMessage'])
            ->orderByDesc('last_message_at')
            ->get();
        $caseload = Patient::assignedTo($clinician)->with('user')->get();

        return view('messages.show', compact('conversation', 'conversations', 'caseload'));
    }

    public function store(Request $request, Conversation $conversation): RedirectResponse|JsonResponse
    {
        Gate::authorize('send', $conversation);

        $validated = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $message = $this->messages->send($conversation, $request->user(), $validated['body']);

        app(ActivityLogService::class)->log($request->user(), 'message.sent', $message);

        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'id' => $message->id,
                    'conversation_id' => $message->conversation_id,
                    'sender_id' => $message->sender_id,
                    'body' => $message->body,
                    'created_at' => $message->created_at?->toIso8601String(),
                    'created_at_label' => $message->created_at?->format('M j, g:i A'),
                ],
            ], 201);
        }

        return redirect()->route('messages.show', $conversation);
    }

    private function currentClinician(Request $request): Clinician
    {
        $clinician = $request->user()->clinician;

        abort_unless($clinician !== null, 403, 'No clinician profile.');

        return $clinician;
    }
}
