import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../providers/auth_provider.dart';
import '../../providers/appointment_provider.dart';
import '../../providers/assignment_provider.dart';
import '../../providers/notification_provider.dart';

class DashboardScreen extends ConsumerWidget {
  const DashboardScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final authState = ref.watch(authProvider);
    final appointments = ref.watch(appointmentsProvider);
    final assignments = ref.watch(assignmentsProvider);
    final notifications = ref.watch(notificationsProvider);

    final user = authState.user;
    final pendingAppointments =
        appointments.valueOrNull?.where((a) => a.status == 'pending' || a.status == 'approved').length ?? 0;
    final pendingAssignments =
        assignments.valueOrNull?.where((a) => !a.isSubmitted).length ?? 0;
    final unreadNotifications =
        notifications.valueOrNull?.where((n) => !n.isRead).length ?? 0;

    return Scaffold(
      appBar: AppBar(
        title: const Text('TheraConnect'),
        actions: [
          IconButton(
            icon: const Icon(Icons.notifications_outlined),
            onPressed: () => context.push('/notifications'),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async {
          await Future.wait([
            ref.read(appointmentsProvider.notifier).loadAppointments(),
            ref.read(assignmentsProvider.notifier).loadAssignments(),
            ref.read(notificationsProvider.notifier).loadNotifications(),
          ]);
        },
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Card(
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  children: [
                    CircleAvatar(
                      radius: 32,
                      backgroundColor: Theme.of(context).colorScheme.primaryContainer,
                      child: Icon(Icons.person, size: 32, color: Theme.of(context).colorScheme.onPrimaryContainer),
                    ),
                    const SizedBox(height: 12),
                    Text(
                      user?.name ?? 'Patient',
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                    Text(
                      user?.email ?? '',
                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                            color: Theme.of(context).colorScheme.onSurfaceVariant,
                          ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),
            Text(
              'Overview',
              style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: _StatCard(
                    icon: Icons.calendar_today,
                    label: 'Appointments',
                    value: '$pendingAppointments',
                    color: Theme.of(context).colorScheme.primary,
                    onTap: () => context.push('/appointments'),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _StatCard(
                    icon: Icons.assignment,
                    label: 'Pending',
                    value: '$pendingAssignments',
                    color: Theme.of(context).colorScheme.tertiary,
                    onTap: () => context.push('/assignments'),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: _StatCard(
                    icon: Icons.notifications,
                    label: 'Notifications',
                    value: '$unreadNotifications',
                    color: Theme.of(context).colorScheme.secondary,
                    onTap: () => context.push('/notifications'),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _StatCard(
                    icon: Icons.chat,
                    label: 'Chatbot',
                    value: 'Help',
                    color: Theme.of(context).colorScheme.primary,
                    onTap: () {
                      final shell = StatefulShellRoute.of(context);
                      shell.goBranch(3);
                    },
                  ),
                ),
              ],
            ),
            const SizedBox(height: 24),
            Text(
              'Upcoming Appointments',
              style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            appointments.when(
              data: (data) {
                final active = data.where((a) => a.status == 'approved' || a.status == 'pending').toList();
                if (active.isEmpty) {
                  return Card(
                    child: Padding(
                      padding: const EdgeInsets.all(24),
                      child: Column(
                        children: [
                          Icon(Icons.event_busy, size: 40, color: Theme.of(context).colorScheme.onSurfaceVariant),
                          const SizedBox(height: 8),
                          Text('No upcoming appointments', style: Theme.of(context).textTheme.bodyMedium),
                          const SizedBox(height: 8),
                          FilledButton.tonal(
                            onPressed: () => context.push('/schedule'),
                            child: const Text('Book Appointment'),
                          ),
                        ],
                      ),
                    ),
                  );
                }
                return Column(
                  children: active.take(3).map((a) => Card(
                    child: ListTile(
                      leading: const Icon(Icons.event),
                      title: Text(a.status == 'approved' ? 'Approved' : 'Pending'),
                      subtitle: Text(a.scheduledAt ?? a.requestedAt ?? 'No date'),
                      trailing: a.mode == 'online'
                          ? const Icon(Icons.videocam)
                          : const Icon(Icons.person),
                    ),
                  )).toList(),
                );
              },
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (e, _) => Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Text('Failed to load: $e'),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _StatCard extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;
  final Color color;
  final VoidCallback onTap;

  const _StatCard({
    required this.icon,
    required this.label,
    required this.value,
    required this.color,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            children: [
              Icon(icon, color: color, size: 32),
              const SizedBox(height: 8),
              Text(value, style: Theme.of(context).textTheme.headlineMedium?.copyWith(color: color)),
              Text(label, style: Theme.of(context).textTheme.bodySmall),
            ],
          ),
        ),
      ),
    );
  }
}
