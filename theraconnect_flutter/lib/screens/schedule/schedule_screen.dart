import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../providers/appointment_provider.dart';

class ScheduleScreen extends ConsumerStatefulWidget {
  const ScheduleScreen({super.key});

  @override
  ConsumerState<ScheduleScreen> createState() => _ScheduleScreenState();
}

class _ScheduleScreenState extends ConsumerState<ScheduleScreen> {
  DateTime _selectedDate = DateTime.now();

  String get _dateString =>
      '${_selectedDate.year}-${_selectedDate.month.toString().padLeft(2, '0')}-${_selectedDate.day.toString().padLeft(2, '0')}';

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(scheduleSlotsProvider(_dateString).notifier).loadSchedules(_dateString);
    });
  }

  void _changeDate(DateTime newDate) {
    setState(() => _selectedDate = newDate);
    ref.read(scheduleSlotsProvider(_dateString).notifier).loadSchedules(_dateString);
  }

  @override
  Widget build(BuildContext context) {
    final slotsAsync = ref.watch(scheduleSlotsProvider(_dateString));

    return Scaffold(
      appBar: AppBar(
        title: const Text('Schedule'),
        actions: [
          TextButton.icon(
            onPressed: () => context.push('/appointments'),
            icon: const Icon(Icons.list),
            label: const Text('My Appointments'),
          ),
        ],
      ),
      body: Column(
        children: [
          Container(
            padding: const EdgeInsets.all(16),
            color: Theme.of(context).colorScheme.surfaceContainerHighest,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                IconButton(
                  icon: const Icon(Icons.chevron_left),
                  onPressed: () => _changeDate(_selectedDate.subtract(const Duration(days: 1))),
                ),
                Column(
                  children: [
                    Text(
                      '${_selectedDate.month}/${_selectedDate.day}/${_selectedDate.year}',
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    TextButton(
                      onPressed: () async {
                        final picked = await showDatePicker(
                          context: context,
                          initialDate: _selectedDate,
                          firstDate: DateTime.now(),
                          lastDate: DateTime.now().add(const Duration(days: 60)),
                        );
                        if (picked != null) {
                          _changeDate(picked);
                        }
                      },
                      child: const Text('Change date'),
                    ),
                  ],
                ),
                IconButton(
                  icon: const Icon(Icons.chevron_right),
                  onPressed: () => _changeDate(_selectedDate.add(const Duration(days: 1))),
                ),
              ],
            ),
          ),
          Expanded(
            child: slotsAsync.when(
              data: (slots) {
                if (slots.isEmpty) {
                  return const Center(child: Text('No slots available for this date.'));
                }
                return ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: slots.length,
                  itemBuilder: (context, index) {
                    final slot = slots[index];
                    return Card(
                      margin: const EdgeInsets.only(bottom: 8),
                      child: ListTile(
                        leading: CircleAvatar(
                          backgroundColor: slot.available
                              ? Theme.of(context).colorScheme.primaryContainer
                              : Theme.of(context).colorScheme.errorContainer,
                          child: Icon(
                            slot.available ? Icons.check_circle : Icons.block,
                            color: slot.available
                                ? Theme.of(context).colorScheme.onPrimaryContainer
                                : Theme.of(context).colorScheme.onErrorContainer,
                          ),
                        ),
                        title: Text(slot.slot),
                        subtitle: Text(slot.clinicianName),
                        trailing: slot.available
                            ? FilledButton.tonal(
                                onPressed: () {
                                  context.push('/schedule/book', extra: {
                                    'date': _dateString,
                                    'slot': slot,
                                  });
                                },
                                child: const Text('Book'),
                              )
                            : const Chip(label: Text('Booked'), visualDensity: VisualDensity.compact),
                      ),
                    );
                  },
                );
              },
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (e, _) => Center(child: Text('Error: $e')),
            ),
          ),
        ],
      ),
    );
  }
}
