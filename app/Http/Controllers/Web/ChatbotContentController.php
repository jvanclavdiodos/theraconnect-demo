<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ChatbotIntent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChatbotContentController extends Controller
{
    public function index(): View
    {
        $intents = ChatbotIntent::with('responses')->paginate(20);

        return view('chatbot-content.index', compact('intents'));
    }

    public function create(): View
    {
        return view('chatbot-content.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'intent_key' => ['required', 'string', 'max:100', 'unique:chatbot_intents'],
            'display_name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:faq,scheduling,smalltalk,fallback'],
            'training_phrases' => ['required', 'array', 'min:1'],
            'training_phrases.*' => ['required', 'string'],
            'responses' => ['required', 'array', 'min:1'],
            'responses.*.response_text' => ['required', 'string'],
            'responses.*.is_fallback' => ['sometimes', 'boolean'],
            'responses.*.priority' => ['sometimes', 'integer'],
        ]);

        $intent = ChatbotIntent::create([
            'intent_key' => $validated['intent_key'],
            'display_name' => $validated['display_name'],
            'category' => $validated['category'],
            'training_phrases' => $validated['training_phrases'],
        ]);

        foreach ($validated['responses'] as $responseData) {
            $intent->responses()->create([
                'response_text' => $responseData['response_text'],
                'is_fallback' => $responseData['is_fallback'] ?? false,
                'priority' => $responseData['priority'] ?? 0,
            ]);
        }

        return redirect()->route('chatbot-content.index')
            ->with('status', 'Intent created successfully.');
    }

    public function edit(ChatbotIntent $intent): View
    {
        $intent->load('responses');

        return view('chatbot-content.edit', compact('intent'));
    }

    public function update(Request $request, ChatbotIntent $intent): RedirectResponse
    {
        $validated = $request->validate([
            'intent_key' => ['required', 'string', 'max:100', 'unique:chatbot_intents,intent_key,'.$intent->id],
            'display_name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:faq,scheduling,smalltalk,fallback'],
            'training_phrases' => ['required', 'array', 'min:1'],
            'training_phrases.*' => ['required', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'responses' => ['required', 'array', 'min:1'],
            'responses.*.response_text' => ['required', 'string'],
            'responses.*.is_fallback' => ['sometimes', 'boolean'],
            'responses.*.priority' => ['sometimes', 'integer'],
        ]);

        $intent->update($validated);

        $intent->responses()->delete();

        foreach ($validated['responses'] as $responseData) {
            $intent->responses()->create([
                'response_text' => $responseData['response_text'],
                'is_fallback' => $responseData['is_fallback'] ?? false,
                'priority' => $responseData['priority'] ?? 0,
            ]);
        }

        return redirect()->route('chatbot-content.index')
            ->with('status', 'Intent updated successfully.');
    }

    public function destroy(ChatbotIntent $intent): RedirectResponse
    {
        $intent->delete();

        return redirect()->route('chatbot-content.index')
            ->with('status', 'Intent deleted successfully.');
    }
}
