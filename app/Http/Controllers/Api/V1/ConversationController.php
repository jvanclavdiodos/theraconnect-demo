<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Services\ActivityLogService;
use App\Services\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ConversationController extends Controller
{
    public function __construct(private MessageService $messages) {}

    public function index(): JsonResponse
    {
        $patient = $this->getPatient();

        $conversations = $this->messages->ensureAssignedConversations($patient);

        return response()->json([
            'data' => ConversationResource::collection($conversations),
        ]);
    }

    /** Open (or create) a thread with one of the patient's assigned clinicians. */
    public function store(Request $request): JsonResponse
    {
        $patient = $this->getPatient();
        $clinicians = $this->messages->assignedCliniciansFor($patient);

        if ($clinicians->isEmpty()) {
            return response()->json([
                'message' => 'You do not have an approved clinician to message yet.',
                'errors' => ['clinician' => ['No approved clinician.']],
            ], 422);
        }

        $clinicianId = $request->integer('clinician_id') ?: null;
        $clinician = $clinicianId
            ? $clinicians->firstWhere('id', $clinicianId)
            : ($clinicians->count() === 1 ? $clinicians->first() : null);

        if (! $clinician) {
            return response()->json([
                'message' => 'Choose an approved clinician to message.',
                'errors' => ['clinician_id' => ['The selected clinician is not assigned to you.']],
            ], 422);
        }

        $conversation = $this->messages->conversationFor($patient, $clinician)
            ->load(['clinician.user', 'latestMessage']);

        return response()->json([
            'data' => new ConversationResource($conversation),
        ], 201);
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('participate', $conversation);

        $messages = $conversation->messages()
            ->with('sender')
            ->orderBy('created_at')
            ->paginate(50);

        $this->messages->markRead($conversation, $request->user());

        return response()->json([
            'data' => MessageResource::collection($messages),
            'meta' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'total' => $messages->total(),
            ],
        ]);
    }

    public function send(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('participate', $conversation);

        $validated = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $message = $this->messages->send($conversation, $request->user(), $validated['body'])
            ->load('sender');

        app(ActivityLogService::class)->log($request->user(), 'message.sent', $message);

        return response()->json(['data' => new MessageResource($message)], 201);
    }

    private function getPatient()
    {
        $patient = auth()->user()->patient;

        if (! $patient) {
            abort(response()->json(['message' => 'Patient profile not found.'], 404));
        }

        return $patient;
    }
}
