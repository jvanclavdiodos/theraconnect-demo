import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/therapy_goal.dart';
import '../services/api/goals_api.dart';
import 'auth_provider.dart';

final goalsApiProvider = Provider<GoalsApi>((ref) {
  return GoalsApi(ref.watch(apiClientProvider));
});

/// The patient's therapy goals (read-only; clinician-authored, GAS-rated).
final goalsProvider = FutureProvider.autoDispose<List<TherapyGoal>>((ref) async {
  return ref.watch(goalsApiProvider).getGoals();
});
