import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../models/api_response.dart';
import '../../providers/message_provider.dart';
import '../../providers/profile_provider.dart';

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
    final patient = ref.watch(profileProvider).valueOrNull;
    final scheme = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(title: const Text('Messages')),
      body: convsAsync.when(
        data: (convs) {
          if (convs.isEmpty) {
            // No assigned clinician — explain rather than silently failing.
            if (patient != null && patient.assignedClinicianId == null) {
              return Center(
                child: Padding(
                  padding: const EdgeInsets.all(32),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.chat_bubble_outline, size: 56, color: scheme.onSurfaceVariant),
                      const SizedBox(height: 16),
                      Text(
                        'No assigned clinician yet',
                        style: Theme.of(context).textTheme.titleMedium,
                        textAlign: TextAlign.center,
                      ),
                      const SizedBox(height: 8),
                      Text(
                        'Once a clinician is assigned to you, you can start a conversation here.',
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
            // Assigned but no thread yet.
            return Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(Icons.chat_bubble_outline, size: 48, color: scheme.onSurfaceVariant),
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
                    backgroundColor: scheme.primaryContainer,
                    child: Icon(Icons.person, color: scheme.onPrimaryContainer),
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
