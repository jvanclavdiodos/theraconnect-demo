import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../models/api_response.dart';
import '../../models/appointment.dart';
import '../../providers/appointment_provider.dart';
import '../../theme/app_theme.dart';
import '../../utils/date_format.dart';

class AppointmentListScreen extends ConsumerWidget {
  const AppointmentListScreen({super.key});

  Color _statusColor(Appointment a, ColorScheme scheme) => switch (a.status) {
    'approved'    => AppTheme.green,
    'pending'     => AppTheme.amber,
    'rescheduled' => AppTheme.blue,
    'completed'   => scheme.primary,
    'rejected'    => scheme.error,
    'cancelled'   => scheme.error,
    _             => scheme.onSurfaceVariant,
  };

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final appointments = ref.watch(appointmentsProvider);
    final scheme = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(title: const Text('My Appointments')),
      body: RefreshIndicator(
        onRefresh: () => ref.read(appointmentsProvider.notifier).loadAppointments(),
        child: appointments.when(
          data: (data) {
            if (data.isEmpty) {
              return ListView(
                children: [
                  SizedBox(height: MediaQuery.of(context).size.height * 0.3),
                  Center(
                    child: Column(
                      children: [
                        Icon(Icons.event_busy, size: 64, color: scheme.onSurfaceVariant),
                        const SizedBox(height: 16),
                        const Text('No appointments yet', style: TextStyle(fontSize: 16)),
                        const SizedBox(height: 8),
                        const Text('Book an appointment from the Schedule tab'),
                      ],
                    ),
                  ),
                ],
              );
            }
            return ListView.builder(
              padding: const EdgeInsets.all(8),
              itemCount: data.length,
              itemBuilder: (context, index) {
                final a = data[index];
                final statusColor = _statusColor(a, scheme);
                return Card(
                  margin: const EdgeInsets.symmetric(vertical: 4, horizontal: 8),
                  child: ListTile(
                    leading: CircleAvatar(
                      backgroundColor: statusColor.withValues(alpha: 0.15),
                      child: Icon(Icons.event, color: statusColor),
                    ),
                    title: Text(formatApptDateTime(a.scheduledAt ?? a.requestedAt)),
                    subtitle: Text(a.clinicianName ?? 'Unassigned'),
                    trailing: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Chip(
                          label: Text(a.statusLabel, style: const TextStyle(fontSize: 12)),
                          backgroundColor: statusColor.withValues(alpha: 0.1),
                        ),
                        const SizedBox(width: 4),
                        const Icon(Icons.chevron_right),
                      ],
                    ),
                    onTap: () => context.push('/appointments/${a.id}'),
                  ),
                );
              },
            );
          },
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (e, _) => Center(child: Text(ApiError.fromException(e).userMessage)),
        ),
      ),
    );
  }
}
