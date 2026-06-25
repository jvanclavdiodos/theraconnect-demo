<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\ChatbotService;
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

    public function message(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $result = $this->chatbot->resolve($validated['message']);

        // Echo the exchange back to the page via flash (stateless, no history table).
        return back()->with('chat', [
            'question' => $validated['message'],
            'answer' => $result,
        ]);
    }
}
