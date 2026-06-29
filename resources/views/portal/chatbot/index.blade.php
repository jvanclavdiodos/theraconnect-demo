@extends('layouts.portal')

@section('title', 'Joy - ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item active">Joy</li>
@endsection

@section('content')
<div class="row justify-content-center" x-data="chatSession({
        csrf: document.querySelector('meta[name=csrf-token]').content,
        endpoint: '{{ route('portal.chatbot.message') }}',
        seed: @js(session('chat')),
    })">
    <div class="col-lg-8">
        <div class="d-flex align-items-center gap-2 mb-1">
            <img src="{{ asset('img/joy-avatar.svg') }}" alt="Joy avatar" width="36" height="36" style="border-radius:10px;">
            <h1 class="tc-page-title mb-0">Joy</h1>
        </div>
        <p class="tc-page-sub">Your TheraConnect assistant - ask about appointments, assignments, or general clinic info.</p>

        <div class="card shadow-sm">
            <div class="card-body d-flex flex-column gap-2" style="min-height: 320px; max-height: 60vh; overflow-y: auto;" x-ref="scroll">
                <template x-if="messages.length === 0">
                    <div class="text-center text-muted py-4">
                        <img src="{{ asset('img/joy-avatar.svg') }}" alt="Joy avatar" width="56" height="56" style="border-radius:16px;">
                        <p class="mt-3 mb-0 fw-semibold text-body">Hi, I'm Joy!</p>
                        <p class="mb-0">Your TheraConnect assistant. How can I help you today?</p>
                    </div>
                </template>

                <template x-for="(m, i) in messages" :key="i">
                    <div>
                        <div class="d-flex justify-content-end mb-2">
                            <div class="p-2 px-3 rounded-3 bg-primary text-white" x-text="m.question" style="max-width:75%;"></div>
                        </div>
                        <div class="d-flex justify-content-start align-items-end gap-2 mb-2">
                            <img src="{{ asset('img/joy-avatar.svg') }}" alt="Joy avatar" width="28" height="28" style="border-radius:8px;flex:0 0 auto;">
                            <div class="p-2 px-3 rounded-3 bg-body-secondary" x-text="replyText(m)" style="max-width:75%;"></div>
                        </div>
                    </div>
                </template>

                <template x-if="awaiting">
                    <div class="d-flex justify-content-start align-items-end gap-2 mb-2">
                        <img src="{{ asset('img/joy-avatar.svg') }}" alt="Joy avatar" width="28" height="28" style="border-radius:8px;flex:0 0 auto;">
                        <div class="p-2 px-3 rounded-3 bg-body-secondary">
                            <span class="spinner-border spinner-border-sm" role="status" aria-label="Joy is typing"></span>
                        </div>
                    </div>
                </template>
            </div>
            <div class="card-footer">
                <form class="d-flex gap-2" @submit.prevent="send()">
                    @csrf
                    <input type="text" name="message" x-model="draft" class="form-control @error('message') is-invalid @enderror"
                           placeholder="Message Joy..." maxlength="1000" required :disabled="awaiting" autofocus>
                    <button class="btn btn-primary" type="submit" :disabled="awaiting || !draft.trim()">
                        <i class="bi bi-send" aria-hidden="true"></i>
                        <span class="visually-hidden">Send</span>
                    </button>
                </form>
                @error('message')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => Alpine.data('chatSession', (opts) => ({
    messages: opts.seed ? [opts.seed] : [],
    draft: '',
    awaiting: false,
    csrf: opts.csrf,
    endpoint: opts.endpoint,
    fallbackReply: "I'm not sure how to help with that yet.",

    replyText(message) {
        const reply = message?.answer?.reply || message?.data?.reply || message?.reply || '';

        return reply.trim() || this.fallbackReply;
    },

    appendExchange(question, result) {
        this.messages = [...this.messages, {
            question: question,
            answer: result || { reply: this.fallbackReply },
        }];

        this.$nextTick(() => this.scrollToLatest());
    },

    scrollToLatest() {
        const scroller = this.$refs.scroll;
        if (scroller) scroller.scrollTop = scroller.scrollHeight;
    },

    async send() {
        const question = this.draft.trim();
        if (!question || this.awaiting) return;

        this.draft = '';
        this.awaiting = true;

        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 15000);

        try {
            const res = await fetch(this.endpoint, {
                method: 'POST',
                signal: controller.signal,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ message: question }),
            });

            const body = await res.json().catch(() => ({}));

            if (!res.ok) {
                throw new Error(body.message || ('HTTP ' + res.status));
            }

            this.appendExchange(
                body.question || question,
                body.answer || body.data || { reply: this.fallbackReply }
            );
        } catch (err) {
            this.appendExchange(question, {
                reply: "Sorry, I couldn't reply just now. Please try again in a moment.",
            });
        } finally {
            clearTimeout(timer);
            this.awaiting = false;
        }
    },
})));
</script>
@endpush
@endsection
