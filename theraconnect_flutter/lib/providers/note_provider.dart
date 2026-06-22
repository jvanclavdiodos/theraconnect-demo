import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/patient_note.dart';
import '../services/api/note_api.dart';
import 'auth_provider.dart';

final noteApiProvider = Provider<NoteApi>((ref) {
  return NoteApi(ref.watch(apiClientProvider));
});

/// Notes the clinician has shared with the patient.
final notesProvider = FutureProvider.autoDispose<List<PatientNote>>((ref) async {
  return ref.watch(noteApiProvider).getNotes();
});
