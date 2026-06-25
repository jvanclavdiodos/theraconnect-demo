import 'package:intl/intl.dart';

/// Date/time formatting helpers for API-supplied date strings.
///
/// The backend serializes timestamps as UTC ISO 8601 (e.g.
/// `2026-06-24T09:00:00.000000Z`) for what are really naive clinic-local times.
/// We deliberately format the parsed value *without* `toLocal()` so the wall
/// clock the clinic entered is the wall clock the patient sees — converting to
/// the device timezone would shift the hour.

final DateFormat _apptFormat = DateFormat('EEE, MMM d · h:mm a');
final DateFormat _dateFormat = DateFormat('EEE, MMM d, y');

/// Formats an ISO date-time string for appointment displays, e.g.
/// `Wed, Jun 24 · 9:00 AM`. Returns [fallback] for null/empty input and the
/// original string if it cannot be parsed (never throws).
String formatApptDateTime(String? iso, {String fallback = 'No date'}) {
  if (iso == null || iso.isEmpty) return fallback;
  final parsed = DateTime.tryParse(iso);
  if (parsed == null) return iso;
  return _apptFormat.format(parsed);
}

/// Formats a date-only string (`yyyy-MM-dd`) for booking displays, e.g.
/// `Wed, Jun 24, 2026`. Returns [fallback] for null/empty input and the
/// original string if it cannot be parsed (never throws).
String formatYmdDate(String? ymd, {String fallback = ''}) {
  if (ymd == null || ymd.isEmpty) return fallback;
  final parsed = DateTime.tryParse(ymd);
  if (parsed == null) return ymd;
  return _dateFormat.format(parsed);
}
