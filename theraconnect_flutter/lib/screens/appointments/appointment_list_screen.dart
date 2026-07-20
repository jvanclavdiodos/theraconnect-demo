import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../models/api_response.dart';
import '../../models/appointment.dart';
import '../../providers/appointment_provider.dart';
import '../../theme/app_theme.dart';
import '../../utils/date_format.dart';

class AppointmentListScreen extends ConsumerStatefulWidget {
  const AppointmentListScreen({super.key});

  @override
  ConsumerState<AppointmentListScreen> createState() =>
      _AppointmentListScreenState();
}

class _AppointmentListScreenState extends ConsumerState<AppointmentListScreen> {
  String? _status;
  String? _mode;
  String _direction = 'desc';
  bool _loadingMore = false;

  static const _statuses = <String, String>{
    'pending': 'Pending',
    'approved': 'Approved',
    'rescheduled': 'Rescheduled',
    'completed': 'Completed',
    'rejected': 'Rejected',
    'cancelled': 'Cancelled',
    'no_show': 'No-show',
  };

  @override
  void initState() {
    super.initState();
    Future.microtask(_load);
  }

  Future<void> _load() {
    return ref.read(appointmentListProvider.notifier).loadAppointments(
          status: _status,
          mode: _mode,
          direction: _direction,
          updateFilters: true,
        );
  }

  Future<void> _loadMore() async {
    if (_loadingMore) return;
    setState(() => _loadingMore = true);
    final error = await ref.read(appointmentListProvider.notifier).loadMore();
    if (!mounted) return;
    setState(() => _loadingMore = false);
    if (error != null) {
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(error)));
    }
  }

  void _reset() {
    setState(() {
      _status = null;
      _mode = null;
      _direction = 'desc';
    });
    _load();
  }

  Color _statusColor(Appointment appointment, ColorScheme scheme) =>
      switch (appointment.status) {
        'approved' => AppTheme.green,
        'pending' => AppTheme.amber,
        'rescheduled' => AppTheme.blue,
        'completed' => scheme.primary,
        'rejected' || 'cancelled' || 'no_show' => scheme.error,
        _ => scheme.onSurfaceVariant,
      };

  @override
  Widget build(BuildContext context) {
    final appointments = ref.watch(appointmentListProvider);
    final scheme = Theme.of(context).colorScheme;
    final notifier = ref.read(appointmentListProvider.notifier);
    final hasFilters = _status != null || _mode != null || _direction != 'desc';

    return Scaffold(
      appBar: AppBar(title: const Text('My Appointments')),
      body: Column(
        children: [
          _Filters(
            status: _status,
            mode: _mode,
            direction: _direction,
            statuses: _statuses,
            showReset: hasFilters,
            onStatusChanged: (value) {
              setState(() => _status = value);
              _load();
            },
            onModeChanged: (value) {
              setState(() => _mode = value);
              _load();
            },
            onDirectionChanged: (value) {
              setState(() => _direction = value ?? 'desc');
              _load();
            },
            onReset: _reset,
          ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: _load,
              child: appointments.when(
                data: (data) {
                  if (data.isEmpty) {
                    return ListView(
                      children: [
                        SizedBox(
                            height: MediaQuery.of(context).size.height * 0.2),
                        Center(
                          child: Padding(
                            padding: const EdgeInsets.all(24),
                            child: Column(
                              children: [
                                Icon(Icons.event_busy,
                                    size: 64, color: scheme.onSurfaceVariant),
                                const SizedBox(height: 16),
                                Text(hasFilters
                                    ? 'No appointments match these filters'
                                    : 'No appointments yet'),
                                if (hasFilters) ...[
                                  const SizedBox(height: 8),
                                  TextButton(
                                    onPressed: _reset,
                                    child: const Text('Clear filters'),
                                  ),
                                ] else
                                  const Padding(
                                    padding: EdgeInsets.only(top: 8),
                                    child: Text(
                                        'Book an appointment from the Schedule tab'),
                                  ),
                              ],
                            ),
                          ),
                        ),
                      ],
                    );
                  }

                  return ListView.builder(
                    padding: const EdgeInsets.all(8),
                    itemCount: data.length + (notifier.hasMore ? 1 : 0),
                    itemBuilder: (context, index) {
                      if (index == data.length) {
                        return Padding(
                          padding: const EdgeInsets.all(12),
                          child: OutlinedButton(
                            onPressed: _loadingMore ? null : _loadMore,
                            child: _loadingMore
                                ? const SizedBox(
                                    width: 20,
                                    height: 20,
                                    child: CircularProgressIndicator(
                                        strokeWidth: 2),
                                  )
                                : const Text('Load more'),
                          ),
                        );
                      }

                      final appointment = data[index];
                      final statusColor = _statusColor(appointment, scheme);
                      return Card(
                        margin: const EdgeInsets.symmetric(
                            vertical: 4, horizontal: 8),
                        child: ListTile(
                          leading: CircleAvatar(
                            backgroundColor:
                                statusColor.withValues(alpha: 0.15),
                            child: Icon(Icons.event, color: statusColor),
                          ),
                          title: Text(formatApptDateTime(
                              appointment.scheduledAt ??
                                  appointment.requestedAt)),
                          subtitle:
                              Text(appointment.clinicianName ?? 'Unassigned'),
                          trailing: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Chip(
                                label: Text(appointment.statusLabel,
                                    style: const TextStyle(fontSize: 12)),
                                backgroundColor:
                                    statusColor.withValues(alpha: 0.1),
                              ),
                              const SizedBox(width: 4),
                              const Icon(Icons.chevron_right),
                            ],
                          ),
                          onTap: () =>
                              context.push('/appointments/${appointment.id}'),
                        ),
                      );
                    },
                  );
                },
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (error, _) => Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(ApiError.fromException(error).userMessage),
                        const SizedBox(height: 8),
                        FilledButton(
                            onPressed: _load, child: const Text('Retry')),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Filters extends StatelessWidget {
  final String? status;
  final String? mode;
  final String direction;
  final Map<String, String> statuses;
  final bool showReset;
  final ValueChanged<String?> onStatusChanged;
  final ValueChanged<String?> onModeChanged;
  final ValueChanged<String?> onDirectionChanged;
  final VoidCallback onReset;

  const _Filters({
    required this.status,
    required this.mode,
    required this.direction,
    required this.statuses,
    required this.showReset,
    required this.onStatusChanged,
    required this.onModeChanged,
    required this.onDirectionChanged,
    required this.onReset,
  });

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Theme.of(context).colorScheme.surfaceContainerLow,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(12, 8, 12, 12),
        child: Column(
          children: [
            Row(
              children: [
                Expanded(
                  child: DropdownButtonFormField<String?>(
                    initialValue: status,
                    decoration: const InputDecoration(
                        labelText: 'Status', isDense: true),
                    items: [
                      const DropdownMenuItem(value: null, child: Text('All')),
                      ...statuses.entries.map((entry) => DropdownMenuItem(
                            value: entry.key,
                            child: Text(entry.value),
                          )),
                    ],
                    onChanged: onStatusChanged,
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: DropdownButtonFormField<String?>(
                    initialValue: mode,
                    decoration: const InputDecoration(
                        labelText: 'Meeting type', isDense: true),
                    items: const [
                      DropdownMenuItem(value: null, child: Text('All')),
                      DropdownMenuItem(value: 'online', child: Text('Online')),
                      DropdownMenuItem(
                          value: 'in_person', child: Text('In-person')),
                    ],
                    onChanged: onModeChanged,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Expanded(
                  child: DropdownButtonFormField<String>(
                    initialValue: direction,
                    decoration: const InputDecoration(
                        labelText: 'Date order', isDense: true),
                    items: const [
                      DropdownMenuItem(
                          value: 'desc', child: Text('Newest first')),
                      DropdownMenuItem(
                          value: 'asc', child: Text('Oldest first')),
                    ],
                    onChanged: onDirectionChanged,
                  ),
                ),
                if (showReset) ...[
                  const SizedBox(width: 8),
                  IconButton(
                    onPressed: onReset,
                    tooltip: 'Clear filters',
                    icon: const Icon(Icons.filter_alt_off),
                  ),
                ],
              ],
            ),
          ],
        ),
      ),
    );
  }
}
