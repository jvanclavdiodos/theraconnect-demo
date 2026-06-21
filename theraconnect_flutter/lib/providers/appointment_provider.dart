import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/appointment.dart';
import '../models/clinician.dart';
import '../models/schedule_slot.dart';
import '../models/api_response.dart';
import '../services/cache_service.dart';
import '../services/api/appointment_api.dart';
import 'auth_provider.dart';

final appointmentApiProvider = Provider<AppointmentApi>((ref) {
  final client = ref.watch(apiClientProvider);
  return AppointmentApi(client);
});

/// Clinicians for the clinician-first booking flow.
final cliniciansProvider = FutureProvider.autoDispose<List<Clinician>>((ref) async {
  return ref.watch(appointmentApiProvider).getClinicians();
});

class AppointmentNotifier extends StateNotifier<AsyncValue<List<Appointment>>> {
  final AppointmentApi _api;
  final CacheService _cache;

  AppointmentNotifier(this._api, this._cache) : super(const AsyncValue.loading()) {
    loadFromCache();
  }

  void loadFromCache() {
    final cached = _cache.getList<Appointment>('appointments', Appointment.fromJson);
    if (cached != null) {
      state = AsyncValue.data(cached);
    }
  }

  Future<void> loadAppointments({int page = 1}) async {
    state = const AsyncValue.loading();
    try {
      final result = await _api.getAppointments(page: page);
      _cache.put('appointments', result.appointments.map((a) => a.toJson()).toList());
      state = AsyncValue.data(result.appointments);
    } catch (e) {
      if (e is ApiError) {
        state = AsyncValue.error(e.userMessage, StackTrace.current);
      } else {
        state = AsyncValue.error(e.toString(), StackTrace.current);
      }
    }
  }

  Future<String?> createAppointment({
    required String requestedAt,
    String mode = 'in_person',
    String? reason,
    int? clinicianId,
  }) async {
    try {
      await _api.createAppointment(
        requestedAt: requestedAt,
        mode: mode,
        reason: reason,
        clinicianId: clinicianId,
      );
      await loadAppointments();
      return null;
    } on ApiError catch (e) {
      return e.userMessage;
    }
  }

  Future<String?> cancelAppointment(int id) async {
    try {
      await _api.cancelAppointment(id);
      await loadAppointments();
      return null;
    } on ApiError catch (e) {
      return e.userMessage;
    }
  }
}

final appointmentsProvider =
    StateNotifierProvider<AppointmentNotifier, AsyncValue<List<Appointment>>>((ref) {
  return AppointmentNotifier(
    ref.watch(appointmentApiProvider),
    ref.watch(cacheServiceProvider),
  );
});

class ScheduleNotifier extends StateNotifier<AsyncValue<List<ScheduleSlot>>> {
  final AppointmentApi _api;

  ScheduleNotifier(this._api) : super(const AsyncValue.data([]));

  Future<void> loadSchedules(String date) async {
    state = const AsyncValue.loading();
    try {
      final slots = await _api.getSchedules(date);
      state = AsyncValue.data(slots);
    } catch (e) {
      if (e is ApiError) {
        state = AsyncValue.error(e.userMessage, StackTrace.current);
      } else {
        state = AsyncValue.error(e.toString(), StackTrace.current);
      }
    }
  }
}

final scheduleSlotsProvider =
    StateNotifierProvider.family.autoDispose<ScheduleNotifier, AsyncValue<List<ScheduleSlot>>, String>(
        (ref, date) {
  final api = ref.watch(appointmentApiProvider);
  return ScheduleNotifier(api);
});

final appointmentDetailProvider = FutureProvider.family.autoDispose<Appointment, int>((ref, id) async {
  final api = ref.watch(appointmentApiProvider);
  return await api.getAppointment(id);
});
