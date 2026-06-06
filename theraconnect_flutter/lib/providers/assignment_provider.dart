import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/assignment.dart';
import '../models/api_response.dart';
import '../services/cache_service.dart';
import '../services/api/assignment_api.dart';
import 'auth_provider.dart';

final assignmentApiProvider = Provider<AssignmentApi>((ref) {
  final client = ref.watch(apiClientProvider);
  return AssignmentApi(client);
});

class AssignmentNotifier extends StateNotifier<AsyncValue<List<Assignment>>> {
  final AssignmentApi _api;
  final CacheService _cache;

  AssignmentNotifier(this._api, this._cache) : super(const AsyncValue.loading()) {
    loadFromCache();
  }

  void loadFromCache() {
    final cached = _cache.getList<Assignment>('assignments', Assignment.fromJson);
    if (cached != null) {
      state = AsyncValue.data(cached);
    }
  }

  Future<void> loadAssignments({int page = 1}) async {
    state = const AsyncValue.loading();
    try {
      final result = await _api.getAssignments(page: page);
      _cache.put('assignments', result.assignments.map((a) => a.toJson()).toList());
      state = AsyncValue.data(result.assignments);
    } catch (e) {
      if (e is ApiError) {
        state = AsyncValue.error(e.userMessage, StackTrace.current);
      } else {
        state = AsyncValue.error(e.toString(), StackTrace.current);
      }
    }
  }

  Future<String?> submitAssignment(int assignmentId, {String? content, String? filePath}) async {
    try {
      await _api.submitAssignment(assignmentId, content: content, filePath: filePath);
      await loadAssignments();
      return null;
    } on ApiError catch (e) {
      return e.userMessage;
    }
  }
}

final assignmentsProvider =
    StateNotifierProvider<AssignmentNotifier, AsyncValue<List<Assignment>>>((ref) {
  return AssignmentNotifier(
    ref.watch(assignmentApiProvider),
    ref.watch(cacheServiceProvider),
  );
});

final assignmentDetailProvider = FutureProvider.family.autoDispose<Assignment, int>((ref, id) async {
  final api = ref.watch(assignmentApiProvider);
  return await api.getAssignment(id);
});
