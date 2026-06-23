import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../models/api_response.dart';
import '../../models/appointment.dart';
import '../../providers/appointment_provider.dart';
import '../../utils/date_format.dart';

class AppointmentDetailScreen extends ConsumerWidget {
  final int appointmentId;

  const AppointmentDetailScreen({super.key, required this.appointmentId});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final detailAsync = ref.watch(appointmentDetailProvider(appointmentId));

    return detailAsync.when(
      data: (a) => _buildContent(context, ref, a),
      loading: () => Scaffold(
        appBar: AppBar(title: const Text('Appointment')),
        body: const Center(child: CircularProgressIndicator()),
      ),
      error: (e, _) => Scaffold(
        appBar: AppBar(title: const Text('Appointment')),
        body: Center(child: Text(ApiError.fromException(e).userMessage)),
      ),
    );
  }

  Widget _buildContent(BuildContext context, WidgetRef ref, Appointment appointment) {
    final canCancel = appointment.status == 'pending' || appointment.status == 'approved';
    // Server gates the link: meeting_link is only present (and active) within 5h
    // of the appointment; expired online links are dropped to null.
    final canJoin = appointment.meetingLinkActive && appointment.meetingLink != null;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Appointment'),
        actions: canCancel
            ? [
                IconButton(
                  icon: const Icon(Icons.cancel),
                  tooltip: 'Cancel',
                  onPressed: () => _cancel(context, ref),
                ),
              ]
            : null,
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                children: [
                  Chip(label: Text(appointment.statusLabel)),
                  const SizedBox(height: 8),
                  Text(
                    formatApptDateTime(appointment.scheduledAt ?? appointment.requestedAt),
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 4),
                  Text('Mode: ${appointment.mode == 'online' ? 'Online' : 'In Person'}'),
                ],
              ),
            ),
          ),
          if (canJoin) ...[
            const SizedBox(height: 16),
            SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                icon: const Icon(Icons.videocam),
                label: const Text('Join Video Call'),
                onPressed: () => _joinCall(context, appointment.meetingLink!),
              ),
            ),
          ] else if (appointment.meetingLinkExpired) ...[
            const SizedBox(height: 16),
            Card(
              color: Theme.of(context).colorScheme.surfaceContainerHighest,
              child: const Padding(
                padding: EdgeInsets.all(16),
                child: Row(
                  children: [
                    Icon(Icons.videocam_off_outlined),
                    SizedBox(width: 8),
                    Expanded(child: Text('This meeting link has expired.')),
                  ],
                ),
              ),
            ),
          ],
          const SizedBox(height: 16),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Details', style: Theme.of(context).textTheme.titleMedium),
                  const Divider(),
                  _DetailRow(label: 'Clinician', value: appointment.clinicianName ?? 'Not assigned'),
                  _DetailRow(label: 'Mode', value: appointment.mode == 'online' ? 'Online' : 'In Person'),
                  if (appointment.meetingLink != null && !canJoin) _DetailRow(label: 'Meeting Link', value: appointment.meetingLink!),
                  if (appointment.reason != null) _DetailRow(label: 'Reason', value: appointment.reason!),
                  if (appointment.clinicNotes != null) _DetailRow(label: 'Notes', value: appointment.clinicNotes!),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _joinCall(BuildContext context, String url) async {
    final uri = Uri.parse(url);
    final launched = await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (!launched && context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Could not open the video call.'), backgroundColor: Colors.red),
      );
    }
  }

  Future<void> _cancel(BuildContext context, WidgetRef ref) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Cancel Appointment'),
        content: const Text('Are you sure you want to cancel this appointment?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('No')),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Yes, Cancel')),
        ],
      ),
    );

    if (confirmed == true && context.mounted) {
      final error = await ref.read(appointmentsProvider.notifier).cancelAppointment(appointmentId);
      if (context.mounted) {
        if (error != null) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(error), backgroundColor: Colors.red),
          );
        } else {
          ref.invalidate(appointmentDetailProvider(appointmentId));
          ref.read(appointmentsProvider.notifier).loadAppointments();
        }
      }
    }
  }
}

class _DetailRow extends StatelessWidget {
  final String label;
  final String value;

  const _DetailRow({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 100,
            child: Text(label, style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: Theme.of(context).colorScheme.onSurfaceVariant,
                )),
          ),
          Expanded(child: Text(value)),
        ],
      ),
    );
  }
}
