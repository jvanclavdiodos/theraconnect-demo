import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../models/api_response.dart';
import '../../models/message.dart';
import '../../providers/auth_provider.dart';
import '../../providers/message_provider.dart';
import '../../providers/realtime_provider.dart';
import '../../services/realtime_service.dart';
import '../../widgets/offline_banner.dart';

class MessageThreadScreen extends ConsumerStatefulWidget {
  final String conversationId;
  final String? title;

  const MessageThreadScreen(
      {super.key, required this.conversationId, this.title});

  @override
  ConsumerState<MessageThreadScreen> createState() =>
      _MessageThreadScreenState();
}

class _MessageThreadScreenState extends ConsumerState<MessageThreadScreen> {
  List<Message> _messages = [];
  bool _loading = true;
  bool _refreshing = false;
  bool _refreshQueued = false;
  bool _sending = false;
  bool _offline = false;
  String? _loadError;
  DateTime? _cachedAt;
  final _controller = TextEditingController();
  final _scrollController = ScrollController();
  late final RealtimeService _realtime;
  StreamSubscription<RealtimeEvent>? _realtimeSubscription;

  @override
  void initState() {
    super.initState();
    _realtime = ref.read(realtimeServiceProvider);
    unawaited(_realtime.subscribeConversation(widget.conversationId));
    _realtimeSubscription = _realtime.events
        .where((event) =>
            event.name == 'connected' ||
            (event.name == 'message.created' &&
                event.data['conversation_public_id'] == widget.conversationId))
        .listen((_) => _load());
    _load();
  }

  @override
  void dispose() {
    _realtimeSubscription?.cancel();
    unawaited(_realtime.unsubscribeConversation(widget.conversationId));
    _controller.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    if (_refreshing) {
      _refreshQueued = true;
      return;
    }
    final shouldScrollToBottom = _isAtBottom;
    _refreshing = true;
    try {
      final messages =
          await ref.read(messageApiProvider).getMessages(widget.conversationId);
      if (!mounted) return;
      final userId = ref.read(authProvider).user?.id;
      if (userId != null) {
        await ref
            .read(messageCacheServiceProvider)
            .saveMessages(userId, widget.conversationId, messages);
      }
      if (!mounted) return;
      setState(() {
        _messages = messages;
        _loading = false;
        _offline = false;
        _loadError = null;
        _cachedAt = null;
      });
      if (shouldScrollToBottom) _scrollToBottom();
    } catch (e) {
      if (!mounted) return;
      final error = ApiError.fromException(e);
      final userId = ref.read(authProvider).user?.id;
      final cached = error.isNetworkError && userId != null
          ? await ref
              .read(messageCacheServiceProvider)
              .readMessages(userId, widget.conversationId)
          : null;
      if (!mounted) return;
      setState(() {
        _loading = false;
        _offline = error.isNetworkError;
        _loadError = cached == null ? error.userMessage : null;
        if (cached != null) {
          _messages = cached.messages;
          _cachedAt = cached.savedAt;
        }
      });
    } finally {
      _refreshing = false;
      if (_refreshQueued) {
        _refreshQueued = false;
        unawaited(_load());
      }
    }
  }

  Future<void> _send() async {
    final body = _controller.text.trim();
    if (body.isEmpty || _sending || _offline) return;
    final shouldScrollToBottom = _isAtBottom;
    setState(() => _sending = true);
    try {
      final message = await ref
          .read(messageApiProvider)
          .sendMessage(widget.conversationId, body);
      if (!mounted) return;
      setState(() {
        if (!_messages.any((existing) => existing.id == message.id)) {
          _messages = [..._messages, message];
        }
        _controller.clear();
        _sending = false;
      });
      final userId = ref.read(authProvider).user?.id;
      if (userId != null) {
        await ref
            .read(messageCacheServiceProvider)
            .saveMessages(userId, widget.conversationId, _messages);
      }
      if (shouldScrollToBottom) _scrollToBottom();
      ref.invalidate(conversationsProvider);
    } catch (e) {
      if (!mounted) return;
      final error = ApiError.fromException(e);
      setState(() {
        _sending = false;
        _offline = error.isNetworkError;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(error.userMessage)),
      );
    }
  }

  bool get _isAtBottom {
    if (!_scrollController.hasClients) return true;

    return _scrollController.position.maxScrollExtent -
            _scrollController.position.pixels <=
        80;
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted || !_scrollController.hasClients) return;
      _scrollController.jumpTo(_scrollController.position.maxScrollExtent);
    });
  }

  @override
  Widget build(BuildContext context) {
    ref.listen<int>(messageRefreshProvider, (_, __) => unawaited(_load()));

    return Scaffold(
      appBar: AppBar(title: Text(widget.title ?? 'Messages')),
      body: Column(
        children: [
          if (_offline)
            OfflineBanner(
              message: _cachedAt == null
                  ? 'You are offline. Connect to the internet to load messages.'
                  : 'You are offline. Showing messages saved on this device.',
              onRetry: _load,
            ),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _loadError != null
                    ? _loadErrorView(context)
                    : _messages.isEmpty
                        ? const Center(
                            child: Text('No messages yet. Say hello.'))
                        : RefreshIndicator(
                            onRefresh: _load,
                            child: ListView.builder(
                              controller: _scrollController,
                              padding: const EdgeInsets.all(12),
                              itemCount: _messages.length,
                              itemBuilder: (context, i) =>
                                  _bubble(context, _messages[i]),
                            ),
                          ),
          ),
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.all(8),
              child: Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _controller,
                      enabled: !_offline,
                      minLines: 1,
                      maxLines: 4,
                      textInputAction: TextInputAction.send,
                      onSubmitted: (_) => _send(),
                      decoration: InputDecoration(
                        hintText: _offline
                            ? 'Connect to the internet to send messages'
                            : 'Type a message...',
                        border: OutlineInputBorder(),
                        isDense: true,
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  IconButton.filled(
                    onPressed: _sending || _offline ? null : _send,
                    icon: _sending
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(strokeWidth: 2))
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

  Widget _loadErrorView(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              Icons.cloud_off_outlined,
              size: 48,
              color: Theme.of(context).colorScheme.onSurfaceVariant,
            ),
            const SizedBox(height: 16),
            Text(_loadError!, textAlign: TextAlign.center),
            const SizedBox(height: 16),
            FilledButton.icon(
              onPressed: _load,
              icon: const Icon(Icons.refresh),
              label: const Text('Try again'),
            ),
          ],
        ),
      ),
    );
  }

  Widget _bubble(BuildContext context, Message m) {
    final scheme = Theme.of(context).colorScheme;
    return Align(
      alignment: m.isMine ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        margin: const EdgeInsets.symmetric(vertical: 4),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        constraints:
            BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.75),
        decoration: BoxDecoration(
          color: m.isMine ? scheme.primary : scheme.surfaceContainerHighest,
          borderRadius: BorderRadius.circular(12),
        ),
        child: Text(
          m.body,
          style:
              TextStyle(color: m.isMine ? scheme.onPrimary : scheme.onSurface),
        ),
      ),
    );
  }
}
