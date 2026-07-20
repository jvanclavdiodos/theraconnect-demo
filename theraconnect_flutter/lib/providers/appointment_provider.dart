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
final cliniciansProvider =
    FutureProvider.autoDispose<List<Clinician>>((ref) async {
  return ref.watch(appointmentApiProvider).getClinicians();
});

class AppointmentNotifier extends StateNotifier<AsyncValue<List<Appointment>>> {
  final AppointmentApi _api;
  final CacheService _cache;
  final bool _useCache;
  int _currentPage = 1;
  int _lastPage = 1;
  String? _status;
  String? _mode;
  String _direction = 'desc';
  bool _loadingMore = false;

  AppointmentNotifier(this._api, this._cache, {bool useCache = true})
      : _useCache = useCache,
        super(const AsyncValue.loading()) {
    if (_useCache) loadFromCache();
  }

  String? get statusFilter => _status;
  String? get modeFilter => _mode;
  String get direction => _direction;
  bool get hasMore => _currentPage < _lastPage;
  bool get isLoadingMore => _loadingMore;

  void loadFromCache() {
    final cached =
        _cache.getList<Appointment>('appointments', Appointment.fromJson);
    if (cached != null) {
      state = AsyncValue.data(cached);
    }
  }

  Future<void> loadAppointments({
    int page = 1,
    String? status,
    String? mode,
    String? direction,
    bool updateFilters = false,
  }) async {
    if (updateFilters) {
      _status = status;
      _mode = mode;
      _direction = direction ?? 'desc';
    }

    state = const AsyncValue.loading();
    try {
      final result = await _api.getAppointments(
        page: page,
        status: _status,
        mode: _mode,
        direction: _direction,
      );
      _currentPage = result.currentPage;
      _lastPage = result.lastPage;
      if (_useCache &&
          _status == null &&
          _mode == null &&
          _direction == 'desc') {
        _cache.put('appointments',
            result.appointments.map((a) => a.toJson()).toList());
      }
      state = AsyncValue.data(result.appointments);
    } catch (e) {
      // ApiError.fromException returns the structured backend message when the
      // API client parsed the response into an ApiError, or a clean generic
      // "Something went wrong." for any other exception type (TypeError, etc.)
      // — never leak internal type names to the UI.
      state = AsyncValue.error(
          ApiError.fromException(e).userMessage, StackTrace.current);
    }
  }

  Future<String?> loadMore() async {
    if (_loadingMore || !hasMore || state.valueOrNull == null) return null;

    _loadingMore = true;
    try {
      final result = await _api.getAppointments(
        page: _currentPage + 1,
        status: _status,
        mode: _mode,
        direction: _direction,
      );
      _currentPage = result.currentPage;
      _lastPage = result.lastPage;
      final byId = <int, Appointment>{
        for (final appointment in state.valueOrNull ?? <Appointment>[])
          appointment.id: appointment,
        for (final appointment in result.appointments)
          appointment.id: appointment,
      };
      state = AsyncValue.data(byId.values.toList());
      return null;
    } catch (e) {
      return ApiError.fromException(e).userMessage;
    } finally {
      _loadingMore = false;
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
    StateNotifierProvider<AppointmentNotifier, AsyncValue<List<Appointment>>>(
        (ref) {
  return AppointmentNotifier(
    ref.watch(appointmentApiProvider),
    ref.watch(cacheServiceProvider),
  );
});

final appointmentListProvider =
    StateNotifierProvider<AppointmentNotifier, AsyncValue<List<Appointment>>>(
        (ref) {
  return AppointmentNotifier(
    ref.watch(appointmentApiProvider),
    ref.watch(cacheServiceProvider),
    useCache: false,
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
      state = AsyncValue.error(
          ApiError.fromException(e).userMessage, StackTrace.current);
    }
  }
}

final scheduleSlotsProvider = StateNotifierProvider.family
    .autoDispose<ScheduleNotifier, AsyncValue<List<ScheduleSlot>>, String>(
        (ref, date) {
  final api = ref.watch(appointmentApiProvider);
  return ScheduleNotifier(api);
});

final appointmentDetailProvider =
    FutureProvider.family.autoDispose<Appointment, int>((ref, id) async {
  final api = ref.watch(appointmentApiProvider);
  return await api.getAppointment(id);
});
