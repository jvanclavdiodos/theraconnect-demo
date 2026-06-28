import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/patient.dart';
import '../models/api_response.dart';
import '../services/cache_service.dart';
import '../services/api/profile_api.dart';
import 'auth_provider.dart';

final profileApiProvider = Provider<ProfileApi>((ref) {
  final client = ref.watch(apiClientProvider);
  return ProfileApi(client);
});

class ProfileNotifier extends StateNotifier<AsyncValue<Patient?>> {
  final ProfileApi _api;
  final CacheService _cache;

  ProfileNotifier(this._api, this._cache) : super(const AsyncValue.loading()) {
    loadFromCache();
  }

  void loadFromCache() {
    final cached = _cache.get<Patient>('patient', Patient.fromJson);
    if (cached != null) {
      state = AsyncValue.data(cached);
    }
  }

  Future<void> loadProfile() async {
    state = const AsyncValue.loading();
    try {
      final patient = await _api.getProfile();
      _cache.put('patient', patient.toJson());
      state = AsyncValue.data(patient);
    } catch (e) {
      state = AsyncValue.error(ApiError.fromException(e).userMessage, StackTrace.current);
    }
  }

  Future<String?> uploadAvatar(String filePath) async {
    try {
      final patient = await _api.uploadAvatar(filePath);
      _cache.put('patient', patient.toJson());
      state = AsyncValue.data(patient);
      return null;
    } on ApiError catch (e) {
      return e.userMessage;
    }
  }

  Future<String?> updateProfile({
    String? dateOfBirth,
    String? gender,
    String? educationalAttainment,
    String? employmentStatus,
    String? personalIssues,
    String? contactNo,
    String? address,
    String? emergencyContact,
  }) async {
    try {
      final patient = await _api.updateProfile(
        dateOfBirth: dateOfBirth,
        gender: gender,
        educationalAttainment: educationalAttainment,
        employmentStatus: employmentStatus,
        personalIssues: personalIssues,
        contactNo: contactNo,
        address: address,
        emergencyContact: emergencyContact,
      );
      _cache.put('patient', patient.toJson());
      state = AsyncValue.data(patient);
      return null;
    } on ApiError catch (e) {
      state = AsyncValue.error(e.userMessage, StackTrace.current);
      return e.userMessage;
    }
  }
}

final profileProvider = StateNotifierProvider<ProfileNotifier, AsyncValue<Patient?>>((ref) {
  return ProfileNotifier(
    ref.watch(profileApiProvider),
    ref.watch(cacheServiceProvider),
  );
});
