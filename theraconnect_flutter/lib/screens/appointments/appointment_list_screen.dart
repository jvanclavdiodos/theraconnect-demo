import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../models/api_response.dart';
import '../../models/appointment.dart';
import '../../providers/appointment_provider.dart';

class AppointmentListScreen extends ConsumerWidget {
  const AppointmentListScreen({super.key});

  Color _statusColor(Appointment a, ThemeData theme) {
    switch (a.status) {
      case 'approved':
        return Colors.green;
      case 'pending':
        return Colors.orange;
      case 'rejected':
      case 'cancelled':
        return theme.colorScheme.error;
      case 'rescheduled':
        return Colors.blue;
      case 'completed':
        return Colors.grey;
      default:
        return theme.colorScheme.primary;
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final appointments = ref.watch(appointmentsProvider);

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
                  const Center(
                    child: Column(
                      children: [
                        Icon(Icons.event_busy, size: 64, color: Colors.grey),
                        SizedBox(height: 16),
                        Text('No appointments yet', style: TextStyle(fontSize: 16)),
                        SizedBox(height: 8),
                        Text('Book an appointment from the Schedule tab'),
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
                return Card(
                  margin: const EdgeInsets.symmetric(vertical: 4, horizontal: 8),
                  child: ListTile(
                    leading: CircleAvatar(
                      backgroundColor: _statusColor(a, Theme.of(context)).withOpacity(0.15),
                      child: Icon(Icons.event, color: _statusColor(a, Theme.of(context))),
                    ),
                    title: Text(a.scheduledAt ?? a.requestedAt ?? 'No date'),
                    subtitle: Text(a.clinicianName ?? 'Unassigned'),
                    trailing: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Chip(
                          label: Text(a.statusLabel, style: const TextStyle(fontSize: 12)),
                          backgroundColor: _statusColor(a, Theme.of(context)).withOpacity(0.1),
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
