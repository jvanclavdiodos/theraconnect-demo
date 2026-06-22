import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../models/api_response.dart';
import '../../providers/message_provider.dart';

class InboxScreen extends ConsumerWidget {
  const InboxScreen({super.key});

  Future<void> _open(BuildContext context, WidgetRef ref) async {
    try {
      final conv = await ref.read(messageApiProvider).ensureConversation();
      if (context.mounted) {
        context.push('/messages/${conv.id}', extra: conv.clinicianName);
        ref.invalidate(conversationsProvider);
      }
    } catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(ApiError.fromException(e).userMessage)),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final convsAsync = ref.watch(conversationsProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Messages')),
      body: convsAsync.when(
        data: (convs) {
          if (convs.isEmpty) {
            return Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(Icons.chat_bubble_outline, size: 48, color: Colors.grey),
                  const SizedBox(height: 12),
                  const Text('No messages yet.'),
                  const SizedBox(height: 12),
                  FilledButton.icon(
                    onPressed: () => _open(context, ref),
                    icon: const Icon(Icons.edit),
                    label: const Text('Message your clinician'),
                  ),
                ],
              ),
            );
          }
          return RefreshIndicator(
            onRefresh: () async => ref.refresh(conversationsProvider.future),
            child: ListView.builder(
              itemCount: convs.length,
              itemBuilder: (context, i) {
                final c = convs[i];
                return ListTile(
                  leading: CircleAvatar(
                    backgroundColor: Theme.of(context).colorScheme.primaryContainer,
                    child: Icon(Icons.person, color: Theme.of(context).colorScheme.onPrimaryContainer),
                  ),
                  title: Text(c.clinicianName ?? 'Clinician'),
                  subtitle: Text(
                    c.lastMessage ?? 'No messages yet.',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  trailing: c.unreadCount > 0
                      ? Badge(label: Text('${c.unreadCount}'))
                      : const Icon(Icons.chevron_right),
                  onTap: () {
                    context.push('/messages/${c.id}', extra: c.clinicianName);
                    ref.invalidate(conversationsProvider);
                  },
                );
              },
            ),
          );
        },
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(child: Text(ApiError.fromException(e).userMessage)),
      ),
    );
  }
}
