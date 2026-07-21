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

    /** Show one thread per assigned clinician, defaulting to the first thread. */
    public function index(Request $request): View
    {
        $patient = $request->user()->patient;
        abort_unless($patient !== null, 404);

        $conversations = $this->messages->ensureAssignedConversations($patient);
        $selectedId = $request->integer('conversation');
        $conversation = $selectedId
            ? $conversations->firstWhere('id', $selectedId)
            : $conversations->first();

        abort_if($selectedId && ! $conversation, 404);

        if ($conversation) {
            $conversation->load(['clinician.user', 'patient.user', 'messages.sender']);
            $this->messages->markRead($conversation, $request->user());
        }

        return view('portal.messages.index', compact('conversation', 'conversations'));
    }

    public function send(Request $request, Conversation $conversation): RedirectResponse
    {
        Gate::authorize('send', $conversation);

        $validated = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $message = $this->messages->send($conversation, $request->user(), $validated['body']);

        app(ActivityLogService::class)->log($request->user(), 'message.sent', $message);

        return redirect()->route('portal.messages.index');
    }
}
