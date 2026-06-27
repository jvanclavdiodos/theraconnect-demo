<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\ChatbotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalChatbotController extends Controller
{
    public function __construct(private ChatbotService $chatbot) {}

    public function index(): View
    {
        return view('portal.chatbot.index');
    }

    public function message(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $result = $this->chatbot->resolve($validated['message']);

        // AJAX clients (the embedded chat UI) get the exchange as JSON so they
        // can append it to an in-memory chat history rather than triggering a
        // full page reload (which the prior session-flash approach did —
        // obliterating history after each send).
        if ($request->expectsJson()) {
            return response()->json([
                'question' => $validated['message'],
                'answer' => $result,
            ]);
        }

        // Non-JS fallback: echo the exchange back via flash (single-exchange
        // view, no history preserved across requests).
        return back()->with('chat', [
            'question' => $validated['message'],
            'answer' => $result,
        ]);
    }
}
