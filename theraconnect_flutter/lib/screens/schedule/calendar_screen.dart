import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:table_calendar/table_calendar.dart';
import '../../models/api_response.dart';
import '../../models/clinician.dart';
import '../../models/schedule_slot.dart';
import '../../providers/appointment_provider.dart';

/// Step 2 of booking: pick an open day from the clinician's calendar, then an
/// available time slot. Days with no availability are disabled.
class CalendarScreen extends ConsumerStatefulWidget {
  final Clinician clinician;

  const CalendarScreen({super.key, required this.clinician});

  @override
  ConsumerState<CalendarScreen> createState() => _CalendarScreenState();
}

class _CalendarScreenState extends ConsumerState<CalendarScreen> {
  static const int _horizonDays = 60;

  late final DateTime _firstDay;
  late final DateTime _lastDay;
  DateTime _focusedDay = DateTime.now();
  DateTime? _selectedDay;

  Set<String> _openDays = {};
  bool _loadingDays = true;
  String? _daysError;

  List<ScheduleSlot> _slots = [];
  bool _loadingSlots = false;

  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    _firstDay = DateTime(now.year, now.month, now.day);
    _lastDay = _firstDay.add(const Duration(days: _horizonDays));
    _loadOpenDays();
  }

  String _ymd(DateTime d) =>
      '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  Future<void> _loadOpenDays() async {
    setState(() {
      _loadingDays = true;
      _daysError = null;
    });
    try {
      final dates = await ref.read(appointmentApiProvider).getAvailability(
            clinicianId: widget.clinician.id,
            from: _ymd(_firstDay),
            to: _ymd(_lastDay),
          );
      if (!mounted) return;
      setState(() {
        _openDays = dates.toSet();
        _loadingDays = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _daysError = ApiError.fromException(e).userMessage;
        _loadingDays = false;
      });
    }
  }

  Future<void> _loadSlots(DateTime day) async {
    setState(() {
      _loadingSlots = true;
      _slots = [];
    });
    try {
      final slots = await ref.read(appointmentApiProvider).getSchedules(
            _ymd(day),
            clinicianId: widget.clinician.id,
          );
      if (!mounted) return;
      setState(() {
        _slots = slots;
        _loadingSlots = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() => _loadingSlots = false);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(ApiError.fromException(e).userMessage)),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.clinician.name)),
      body: _loadingDays
          ? const Center(child: CircularProgressIndicator())
          : _daysError != null
              ? Center(child: Text(_daysError!))
              : ListView(
                  children: [
                    TableCalendar(
                      firstDay: _firstDay,
                      lastDay: _lastDay,
                      focusedDay: _focusedDay,
                      selectedDayPredicate: (day) =>
                          _selectedDay != null && isSameDay(_selectedDay, day),
                      enabledDayPredicate: (day) => _openDays.contains(_ymd(day)),
                      calendarFormat: CalendarFormat.month,
                      availableGestures: AvailableGestures.horizontalSwipe,
                      headerStyle: const HeaderStyle(formatButtonVisible: false, titleCentered: true),
                      onDaySelected: (selected, focused) {
                        setState(() {
                          _selectedDay = selected;
                          _focusedDay = focused;
                        });
                        _loadSlots(selected);
                      },
                      onPageChanged: (focused) => _focusedDay = focused,
                    ),
                    const Divider(),
                    _buildSlots(context),
                  ],
                ),
    );
  }

  Widget _buildSlots(BuildContext context) {
    if (_selectedDay == null) {
      return const Padding(
        padding: EdgeInsets.all(24),
        child: Center(child: Text('Select an available day to see open times.')),
      );
    }
    if (_loadingSlots) {
      return const Padding(
        padding: EdgeInsets.all(24),
        child: Center(child: CircularProgressIndicator()),
      );
    }

    final open = _slots.where((s) => s.available).toList();
    if (open.isEmpty) {
      return const Padding(
        padding: EdgeInsets.all(24),
        child: Center(child: Text('No open times left on this day.')),
      );
    }

    return Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Available times', style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: open.map((slot) {
              return ActionChip(
                label: Text(slot.slot),
                avatar: const Icon(Icons.access_time, size: 18),
                onPressed: () {
                  context.push('/schedule/book', extra: {
                    'date': _ymd(_selectedDay!),
                    'slot': slot,
                  });
                },
              );
            }).toList(),
          ),
        ],
      ),
    );
  }
}
