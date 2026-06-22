import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/assessment.dart';
import '../services/api/assessment_api.dart';
import 'auth_provider.dart';

final assessmentApiProvider = Provider<AssessmentApi>((ref) {
  return AssessmentApi(ref.watch(apiClientProvider));
});

/// The patient's questionnaires (pending first, then completed history).
final assessmentsProvider =
    FutureProvider.autoDispose<List<Assessment>>((ref) async {
  return ref.watch(assessmentApiProvider).getAssessments();
});

/// Full content of one questionnaire, for rendering the fillable form.
final assessmentDetailProvider =
    FutureProvider.autoDispose.family<AssessmentDetail, int>((ref, id) async {
  return ref.watch(assessmentApiProvider).getAssessment(id);
});
