import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../models/api_response.dart';
import '../../providers/message_provider.dart';

class InboxScreen extends ConsumerWidget {
  const InboxScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final convsAsync = ref.watch(conversationsProvider);
    final scheme = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(title: const Text('Messages')),
      body: convsAsync.when(
        data: (convs) {
          if (convs.isEmpty) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(32),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(
                      Icons.chat_bubble_outline,
                      size: 56,
                      color: scheme.onSurfaceVariant,
                    ),
                    const SizedBox(height: 16),
                    Text(
                      'No approved clinicians yet',
                      style: Theme.of(context).textTheme.titleMedium,
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'A conversation becomes available after a clinician approves your appointment.',
                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                            color: scheme.onSurfaceVariant,
                          ),
                      textAlign: TextAlign.center,
                    ),
                  ],
                ),
              ),
            );
          }

          return RefreshIndicator(
            onRefresh: () async => ref.refresh(conversationsProvider.future),
            child: ListView.builder(
              itemCount: convs.length,
              itemBuilder: (context, i) {
                final conversation = convs[i];
                return ListTile(
                  leading: CircleAvatar(
                    backgroundColor: scheme.primaryContainer,
                    child: Icon(Icons.person, color: scheme.onPrimaryContainer),
                  ),
                  title: Text(conversation.clinicianName ?? 'Clinician'),
                  subtitle: Text(
                    conversation.lastMessage ?? 'No messages yet.',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  trailing: conversation.unreadCount > 0
                      ? Badge(label: Text('${conversation.unreadCount}'))
                      : const Icon(Icons.chevron_right),
                  onTap: () async {
                    await context.push(
                      '/messages/${conversation.id}',
                      extra: conversation.clinicianName,
                    );
                    ref.invalidate(conversationsProvider);
                  },
                );
              },
            ),
          );
        },
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => Center(
          child: Text(ApiError.fromException(error).userMessage),
        ),
      ),
    );
  }
}
