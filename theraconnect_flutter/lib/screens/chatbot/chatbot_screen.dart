import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../l10n/app_localizations.dart';
import '../../models/chatbot_message.dart';
import '../../providers/chatbot_provider.dart';
import '../../widgets/joy_avatar.dart';

class ChatbotScreen extends ConsumerStatefulWidget {
  const ChatbotScreen({super.key});

  @override
  ConsumerState<ChatbotScreen> createState() => _ChatbotScreenState();
}

class _ChatbotScreenState extends ConsumerState<ChatbotScreen> {
  final _messageController = TextEditingController();
  final _scrollController = ScrollController();
  bool _sending = false;

  @override
  void dispose() {
    _messageController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  void _scrollToBottom() {
    if (_scrollController.hasClients) {
      _scrollController.animateTo(
        _scrollController.position.maxScrollExtent,
        duration: const Duration(milliseconds: 300),
        curve: Curves.easeOut,
      );
    }
  }

  void _sendMessage() {
    if (_sending) return;
    final text = _messageController.text.trim();
    if (text.isEmpty) return;

    setState(() => _sending = true);
    _messageController.clear();
    ref.read(chatbotProvider.notifier).sendMessage(text);
  }

  @override
  Widget build(BuildContext context) {
    final messages = ref.watch(chatbotProvider);
    final l = AppLocalizations.of(context)!;

    ref.listen(chatbotProvider, (_, next) {
      if (next is AsyncData) {
        // Reset _sending ONLY when the bot reply (or the error reply) has
        // actually replaced the typing placeholder. The notifier emits
        // AsyncValue.data([... , ChatMessage(text: '...', isUser: false)])
        // immediately after sendMessage() is called (the placeholder for the
        // typing animation) — if we reset _sending on every AsyncData, the
        // send button gets re-enabled while the API request is still in
        // flight. Infer the pending state by checking the last message's
        // text against the well-known placeholder.
        final messages = next.value ?? <ChatMessage>[];
        final isStillTyping =
            messages.isNotEmpty && messages.last.text == '...' && !messages.last.isUser;
        if (!isStillTyping) {
          setState(() => _sending = false);
        }
        WidgetsBinding.instance.addPostFrameCallback((_) => _scrollToBottom());
      } else if (next is AsyncError) {
        setState(() => _sending = false);
      }
    });

    final messageList = messages.valueOrNull ?? [];

    return Scaffold(
      appBar: AppBar(
        title: Text(l.chatbotTitle),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            tooltip: l.chatbotClearTooltip,
            onPressed: () => ref.read(chatbotProvider.notifier).clearMessages(),
          ),
        ],
      ),
      body: Column(
        children: [
          Expanded(
            child: messageList.isEmpty
                ? Center(
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 32),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        crossAxisAlignment: CrossAxisAlignment.center,
                        children: [
                          const JoyAvatar(size: 72),
                          const SizedBox(height: 16),
                          Text(l.chatbotEmptyPrompt,
                              textAlign: TextAlign.center,
                              style: Theme.of(context).textTheme.titleMedium
                                  ?.copyWith(fontWeight: FontWeight.bold)),
                          const SizedBox(height: 8),
                          Text(l.chatbotEmptyHint,
                              textAlign: TextAlign.center,
                              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                    color: Theme.of(context).colorScheme.onSurfaceVariant,
                                  )),
                        ],
                      ),
                    ),
                  )
                : ListView.builder(
                    controller: _scrollController,
                    padding: const EdgeInsets.all(16),
                    itemCount: messageList.length,
                    itemBuilder: (context, index) {
                      final msg = messageList[index];
                      return Align(
                        alignment: msg.isUser ? Alignment.centerRight : Alignment.centerLeft,
                        child: Container(
                          margin: const EdgeInsets.only(bottom: 8),
                          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                          decoration: BoxDecoration(
                            color: msg.isUser
                                ? Theme.of(context).colorScheme.primaryContainer
                                : Theme.of(context).colorScheme.surfaceContainerHighest,
                            borderRadius: BorderRadius.circular(16),
                          ),
                          constraints: BoxConstraints(
                            maxWidth: MediaQuery.of(context).size.width * 0.75,
                          ),
                          child: Text(msg.text),
                        ),
                      );
                    },
                  ),
          ),
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: Theme.of(context).colorScheme.surfaceContainerLow,
              boxShadow: [
                BoxShadow(
                  color: Theme.of(context).colorScheme.shadow.withValues(alpha: 0.1),
                  blurRadius: 4,
                  offset: const Offset(0, -1),
                ),
              ],
            ),
            child: SafeArea(
              child: Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _messageController,
                      decoration: InputDecoration(
                        hintText: l.chatbotInputHint,
                        border: const OutlineInputBorder(),
                        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                      ),
                      textInputAction: TextInputAction.send,
                      enabled: !_sending,
                      onSubmitted: (_) => _sendMessage(),
                    ),
                  ),
                  const SizedBox(width: 8),
                  FilledButton(
                    onPressed: _sending ? null : _sendMessage,
                    child: _sending
                        ? SizedBox(
                            height: 18,
                            width: 18,
                            child: CircularProgressIndicator(strokeWidth: 2, color: Theme.of(context).colorScheme.onPrimary),
                          )
                        : const Icon(Icons.send),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
