import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../models/api_response.dart';
import '../../providers/message_provider.dart';
import '../../widgets/offline_banner.dart';

class InboxScreen extends ConsumerWidget {
  const InboxScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final convsAsync = ref.watch(conversationsProvider);
    final scheme = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(title: const Text('Messages')),
      body: convsAsync.when(
        data: (snapshot) {
          final convs = snapshot.conversations;
          if (convs.isEmpty) {
            return Column(
              children: [
                if (snapshot.isOffline)
                  OfflineBanner(
                    onRetry: () async =>
                        ref.invalidate(conversationsProvider),
                  ),
                Expanded(
                    child: Center(
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
                          snapshot.isOffline
                              ? 'No conversations saved on this device'
                              : 'No approved clinicians yet',
                          style: Theme.of(context).textTheme.titleMedium,
                          textAlign: TextAlign.center,
                        ),
                        const SizedBox(height: 8),
                        Text(
                          snapshot.isOffline
                              ? 'Connect to the internet to check for new conversations.'
                              : 'A conversation becomes available after a clinician approves your appointment.',
                          style:
                              Theme.of(context).textTheme.bodyMedium?.copyWith(
                                    color: scheme.onSurfaceVariant,
                                  ),
                          textAlign: TextAlign.center,
                        ),
                      ],
                    ),
                  ),
                )),
              ],
            );
          }

          return Column(
            children: [
              if (snapshot.isOffline)
                OfflineBanner(
                  onRetry: () async => ref.invalidate(conversationsProvider),
                ),
              Expanded(
                child: RefreshIndicator(
                  onRefresh: () async =>
                      ref.refresh(conversationsProvider.future),
                  child: ListView.builder(
                    itemCount: convs.length,
                    itemBuilder: (context, i) {
                      final conversation = convs[i];
                      return ListTile(
                        leading: CircleAvatar(
                          backgroundColor: scheme.primaryContainer,
                          child: Icon(Icons.person,
                              color: scheme.onPrimaryContainer),
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
                ),
              ),
            ],
          );
        },
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => _MessageLoadError(
          message: ApiError.fromException(error).userMessage,
          onRetry: () => ref.invalidate(conversationsProvider),
        ),
      ),
    );
  }
}

class _MessageLoadError extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;

  const _MessageLoadError({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.cloud_off_outlined,
                size: 48,
                color: Theme.of(context).colorScheme.onSurfaceVariant),
            const SizedBox(height: 16),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 16),
            FilledButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh),
              label: const Text('Try again'),
            ),
          ],
        ),
      ),
    );
  }
}
