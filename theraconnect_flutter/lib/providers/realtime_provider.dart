import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../services/realtime_service.dart';
import 'auth_provider.dart';

final realtimeServiceProvider = Provider<RealtimeService>((ref) {
  final service = RealtimeService(
    ref.watch(apiClientProvider),
    ref.watch(authServiceProvider),
  );
  ref.onDispose(service.disconnect);

  return service;
});
