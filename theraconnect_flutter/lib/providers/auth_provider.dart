import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../models/clinician.dart';
import '../models/user.dart';
import '../models/patient.dart';
import '../models/api_response.dart';
import '../services/auth_service.dart';
import '../services/cache_service.dart';
import '../services/api_client.dart';
import '../services/api/auth_api.dart';

final authServiceProvider = Provider<AuthService>((ref) => AuthService());

final sharedPreferencesProvider =
    Provider<SharedPreferences>((ref) => throw UnimplementedError('Must be overridden'));

final cacheServiceProvider = Provider<CacheService>((ref) {
  final prefs = ref.watch(sharedPreferencesProvider);
  return CacheService(prefs);
});

final apiClientProvider = Provider<ApiClient>((ref) {
  final authService = ref.watch(authServiceProvider);
  return ApiClient(authService: authService);
});

final authApiProvider = Provider<AuthApi>((ref) {
  final client = ref.watch(apiClientProvider);
  return AuthApi(client);
});

/// Clinicians offered in the sign-up "preferred clinician" picker. Loaded
/// pre-auth from the public directory endpoint.
final registrationCliniciansProvider = FutureProvider<List<Clinician>>((ref) {
  return ref.watch(authApiProvider).fetchClinicians();
});

enum AuthState { unauthenticated, loading, authenticated }

class AuthNotifier
    extends StateNotifier<({AuthState status, User? user, Patient? patient, String? error})> {
  final AuthService _authService;
  final CacheService _cacheService;
  final AuthApi _authApi;

  AuthNotifier(this._authService, this._cacheService, this._authApi)
      : super((status: AuthState.unauthenticated, user: null, patient: null, error: null));

  void setApiClient(ApiClient client) {
    client.onUnauthorized = _handleUnauthorized;
  }

  void _handleUnauthorized() {
    state = (status: AuthState.unauthenticated, user: null, patient: null, error: null);
    _cacheService.clear();
  }

  Future<void> checkAuth() async {
    final hasToken = await _authService.hasToken();
    if (!hasToken) {
      state = (status: AuthState.unauthenticated, user: null, patient: null, error: null);
      return;
    }
    try {
      final profile = await _authApi.me();
      _cacheService.put('user', profile.user.toJson());
      if (profile.patientProfile != null) {
        _cacheService.put('patient', profile.patientProfile!.toJson());
      }
      state = (
        status: AuthState.authenticated,
        user: profile.user,
        patient: profile.patientProfile,
        error: null,
      );
    } on ApiError catch (e) {
      if (e.statusCode == 401) {
        await _authService.clearToken();
        await _cacheService.clear();
        state = (status: AuthState.unauthenticated, user: null, patient: null, error: null);
      }
    } on DioException catch (_) {
      // Network errors during checkAuth should not log out
    }
  }

  Future<String?> login(String email, String password) async {
    state = (status: AuthState.loading, user: null, patient: null, error: null);
    try {
      final result = await _authApi.login(email: email, password: password);
      await _authService.saveToken(result.token);

      final profile = await _authApi.me();
      if (profile.patientProfile != null) {
        _cacheService.put('patient', profile.patientProfile!.toJson());
      }
      _cacheService.put('user', profile.user.toJson());
      state = (
        status: AuthState.authenticated,
        user: profile.user,
        patient: profile.patientProfile,
        error: null,
      );
      return null;
    } on ApiError catch (e) {
      state =
          (status: AuthState.unauthenticated, user: null, patient: null, error: e.userMessage);
      return e.userMessage;
    } catch (e) {
      // Any non-ApiError (parsing, secure storage, platform) must still reset
      // the loading state, otherwise the sign-in spinner hangs forever.
      // Any non-ApiError (parsing, secure storage, platform) must still reset
      // the loading state, otherwise the sign-in spinner hangs forever.
      // Collapse to a patient-friendly message — never leak stack traces /
      // Dio request paths / backend exception text from the caught exception.
      final message = ApiError.fromException(e).userMessage;
      state = (status: AuthState.unauthenticated, user: null, patient: null, error: message);
      return message;
    }
  }

  Future<String?> register(String name, String email, String password,
      String passwordConfirmation,
      {String? contactNo,
      String? gender,
      String? educationalAttainment,
      String? employmentStatus,
      String? personalIssues,
      int? requestedClinicianId}) async {
    state = (status: AuthState.loading, user: null, patient: null, error: null);
    try {
      final result = await _authApi.register(
        name: name,
        email: email,
        password: password,
        passwordConfirmation: passwordConfirmation,
        contactNo: contactNo,
        gender: gender,
        educationalAttainment: educationalAttainment,
        employmentStatus: employmentStatus,
        personalIssues: personalIssues,
        requestedClinicianId: requestedClinicianId,
      );
      await _authService.saveToken(result.token);

      final profile = await _authApi.me();
      if (profile.patientProfile != null) {
        _cacheService.put('patient', profile.patientProfile!.toJson());
      }
      _cacheService.put('user', profile.user.toJson());
      state = (
        status: AuthState.authenticated,
        user: profile.user,
        patient: profile.patientProfile,
        error: null,
      );
      return null;
    } on ApiError catch (e) {
      state =
          (status: AuthState.unauthenticated, user: null, patient: null, error: e.userMessage);
      return e.userMessage;
    } catch (e) {
      // Any non-ApiError (parsing, secure storage, platform) must still reset
      // the loading state, otherwise the sign-up spinner hangs forever.
      // See comment in login() — never leak exception text via $e.toString().
      final message = ApiError.fromException(e).userMessage;
      state = (status: AuthState.unauthenticated, user: null, patient: null, error: message);
      return message;
    }
  }

  /// Change the signed-in patient's password. Returns null on success, or a
  /// user-facing error message (wrong current password, weak new password, …).
  Future<String?> changePassword(
      String currentPassword, String newPassword, String newPasswordConfirmation) async {
    try {
      await _authApi.changePassword(
        currentPassword: currentPassword,
        newPassword: newPassword,
        newPasswordConfirmation: newPasswordConfirmation,
      );
      return null;
    } on ApiError catch (e) {
      return e.userMessage;
    } catch (e) {
      // Collapse to a patient-friendly message — never leak exception text.
      return ApiError.fromException(e).userMessage;
    }
  }

  Future<void> logout() async {
    try {
      await _authApi.logout();
    } catch (_) {}
    await _authService.clearToken();
    await _cacheService.clear();
    state = (status: AuthState.unauthenticated, user: null, patient: null, error: null);
  }

  void clearError() {
    state = (status: state.status, user: state.user, patient: state.patient, error: null);
  }
}

final authProvider = StateNotifierProvider<AuthNotifier,
    ({AuthState status, User? user, Patient? patient, String? error})>((ref) {
  return AuthNotifier(
    ref.watch(authServiceProvider),
    ref.watch(cacheServiceProvider),
    ref.watch(authApiProvider),
  );
});
