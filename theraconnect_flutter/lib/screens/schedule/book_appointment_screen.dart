import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../models/schedule_slot.dart';
import '../../providers/appointment_provider.dart';

class BookAppointmentScreen extends ConsumerStatefulWidget {
  const BookAppointmentScreen({super.key});

  @override
  ConsumerState<BookAppointmentScreen> createState() => _BookAppointmentScreenState();
}

class _BookAppointmentScreenState extends ConsumerState<BookAppointmentScreen> {
  String _mode = 'in_person';
  final _reasonController = TextEditingController();
  bool _submitting = false;

  @override
  void dispose() {
    _reasonController.dispose();
    super.dispose();
  }

  Future<void> _book() async {
    final extra = GoRouterState.of(context).extra as Map<String, dynamic>;
    final slot = extra['slot'] as ScheduleSlot;
    final date = extra['date'] as String;

    final slotStart = slot.slot.split('-')[0];
    final requestedAt = '$date $slotStart:00';

    setState(() => _submitting = true);

    final error = await ref.read(appointmentsProvider.notifier).createAppointment(
          requestedAt: requestedAt,
          mode: _mode,
          reason: _reasonController.text.trim().isEmpty ? null : _reasonController.text.trim(),
          clinicianId: slot.clinicianId,
        );

    if (mounted) {
      setState(() => _submitting = false);
      if (error != null) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(error), backgroundColor: Colors.red),
        );
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Appointment booked successfully!'), backgroundColor: Colors.green),
        );
        context.pop();
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final extra = GoRouterState.of(context).extra as Map<String, dynamic>;
    final slot = extra['slot'] as ScheduleSlot;

    return Scaffold(
      appBar: AppBar(title: const Text('Book Appointment')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                children: [
                  Text(slot.slot, style: Theme.of(context).textTheme.headlineSmall),
                  const SizedBox(height: 4),
                  Text(slot.clinicianName, style: Theme.of(context).textTheme.bodyLarge),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          Text('Mode', style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 8),
          SegmentedButton<String>(
            segments: const [
              ButtonSegment(value: 'in_person', label: Text('In Person'), icon: Icon(Icons.person)),
              ButtonSegment(value: 'online', label: Text('Online'), icon: Icon(Icons.videocam)),
            ],
            selected: {_mode},
            onSelectionChanged: (v) => setState(() => _mode = v.first),
          ),
          const SizedBox(height: 16),
          TextFormField(
            controller: _reasonController,
            maxLines: 3,
            decoration: const InputDecoration(
              labelText: 'Reason for visit (optional)',
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: 24),
          FilledButton(
            onPressed: _submitting ? null : _book,
            style: FilledButton.styleFrom(minimumSize: const Size(double.infinity, 48)),
            child: _submitting
                ? const CircularProgressIndicator(strokeWidth: 2, color: Colors.white)
                : const Text('Confirm Booking'),
          ),
        ],
      ),
    );
  }
}
