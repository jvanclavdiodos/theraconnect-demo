import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../models/api_response.dart';
import '../../providers/notification_provider.dart';

class NotificationListScreen extends ConsumerWidget {
  const NotificationListScreen({super.key});

  IconData _iconForType(String type) {
    switch (type) {
      case 'appointment_approved':
      case 'appointment_reminder':
        return Icons.event_available;
      case 'appointment_rejected':
        return Icons.event_busy;
      case 'appointment_rescheduled':
        return Icons.update;
      case 'assignment_created':
      case 'assignment_deadline':
        return Icons.assignment;
      default:
        return Icons.notifications;
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final notifications = ref.watch(notificationsProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Notifications')),
      body: RefreshIndicator(
        onRefresh: () => ref.read(notificationsProvider.notifier).loadNotifications(),
        child: notifications.when(
          data: (data) {
            if (data.isEmpty) {
              return ListView(
                children: [
                  SizedBox(height: MediaQuery.of(context).size.height * 0.3),
                  Center(
                    child: Column(
                      children: [
                        Icon(Icons.notifications_off, size: 64, color: Theme.of(context).colorScheme.onSurfaceVariant),
                        const SizedBox(height: 16),
                        const Text('No notifications', style: TextStyle(fontSize: 16)),
                      ],
                    ),
                  ),
                ],
              );
            }
            return ListView.separated(
              padding: const EdgeInsets.all(8),
              itemCount: data.length,
              separatorBuilder: (_, __) => const SizedBox(height: 4),
              itemBuilder: (context, index) {
                final n = data[index];
                return Card(
                  margin: EdgeInsets.zero,
                  color: n.isRead ? null : Theme.of(context).colorScheme.primaryContainer.withOpacity(0.3),
                  child: ListTile(
                    leading: CircleAvatar(
                      backgroundColor: n.isRead
                          ? Theme.of(context).colorScheme.surfaceContainerHighest
                          : Theme.of(context).colorScheme.primaryContainer,
                      child: Icon(
                        _iconForType(n.type),
                        color: n.isRead ? Theme.of(context).colorScheme.onSurfaceVariant : Theme.of(context).colorScheme.primary,
                      ),
                    ),
                    title: Text(
                      n.title,
                      style: TextStyle(fontWeight: n.isRead ? FontWeight.normal : FontWeight.bold),
                    ),
                    subtitle: Text(n.body, maxLines: 2, overflow: TextOverflow.ellipsis),
                    trailing: !n.isRead
                        ? IconButton(
                            icon: const Icon(Icons.done),
                            tooltip: 'Mark read',
                            onPressed: () async {
                              final error = await ref
                                  .read(notificationsProvider.notifier)
                                  .markRead(n.id);
                              if (error != null && context.mounted) {
                                ScaffoldMessenger.of(context).showSnackBar(
                                  SnackBar(content: Text(error)),
                                );
                              }
                            },
                          )
                        : null,
                  ),
                );
              },
            );
          },
          loading: () => const Center(child: CircularProgressIndicator()),
          // Belt-and-suspenders: the provider already pre-sanitizes the error
          // to a String before storing it, but routing it through
          // ApiError.fromException here makes the screen robust to future
          // provider refactors that might store the raw exception — never
          // leak internal stack traces / API paths to a patient.
          error: (e, _) => Center(
            child: Text(ApiError.fromException(e).userMessage),
          ),
        ),
      ),
    );
  }
}
