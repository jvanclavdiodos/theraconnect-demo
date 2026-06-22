import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/mood_log.dart';
import '../services/api/mood_api.dart';
import 'auth_provider.dart';

final moodApiProvider = Provider<MoodApi>((ref) {
  return MoodApi(ref.watch(apiClientProvider));
});

/// The patient's recent mood check-ins (newest first).
final moodLogsProvider =
    FutureProvider.autoDispose<List<MoodLog>>((ref) async {
  return ref.watch(moodApiProvider).getMoodLogs();
});
