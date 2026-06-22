import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/downloaded_file.dart';
import '../services/download_service.dart';
import 'auth_provider.dart';

final downloadServiceProvider = Provider<DownloadService>((ref) {
  final client = ref.watch(apiClientProvider);
  final prefs = ref.watch(sharedPreferencesProvider);
  return DownloadService(client.dio, prefs);
});

class DownloadsNotifier extends StateNotifier<List<DownloadedFile>> {
  final DownloadService _service;

  DownloadsNotifier(this._service) : super(const []) {
    state = _service.list();
  }

  void refresh() {
    state = _service.list();
  }

  Future<void> remove(DownloadedFile file) async {
    await _service.remove(file);
    state = _service.list();
  }
}

final downloadsProvider =
    StateNotifierProvider<DownloadsNotifier, List<DownloadedFile>>((ref) {
  return DownloadsNotifier(ref.watch(downloadServiceProvider));
});
