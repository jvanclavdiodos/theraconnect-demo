import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../models/api_response.dart';
import '../../providers/appointment_provider.dart';

/// Step 1 of booking: pick a clinician. Tapping one opens their availability
/// calendar (clinician-first flow).
class ScheduleScreen extends ConsumerWidget {
  const ScheduleScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cliniciansAsync = ref.watch(cliniciansProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Book Appointment'),
        actions: [
          TextButton.icon(
            onPressed: () => context.push('/appointments'),
            icon: const Icon(Icons.list),
            label: const Text('My Appointments'),
          ),
        ],
      ),
      body: cliniciansAsync.when(
        data: (clinicians) {
          if (clinicians.isEmpty) {
            return const Center(child: Text('No clinicians are available right now.'));
          }
          return RefreshIndicator(
            onRefresh: () async => ref.refresh(cliniciansProvider.future),
            child: ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: clinicians.length + 1,
              itemBuilder: (context, index) {
                if (index == 0) {
                  return const Padding(
                    padding: EdgeInsets.only(bottom: 8),
                    child: Text('Choose a clinician to see their available dates and times.'),
                  );
                }
                final clinician = clinicians[index - 1];
                return Card(
                  margin: const EdgeInsets.only(bottom: 8),
                  child: ListTile(
                    leading: CircleAvatar(
                      backgroundColor: Theme.of(context).colorScheme.primaryContainer,
                      child: Icon(
                        Icons.person,
                        color: Theme.of(context).colorScheme.onPrimaryContainer,
                      ),
                    ),
                    title: Text(clinician.name),
                    subtitle: clinician.specialization != null
                        ? Text(clinician.specialization!)
                        : null,
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () => context.push('/schedule/calendar', extra: clinician),
                  ),
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
