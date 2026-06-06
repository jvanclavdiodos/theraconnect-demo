import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/notification_item.dart';
import '../models/api_response.dart';
import '../services/cache_service.dart';
import '../services/api/notification_api.dart';
import 'auth_provider.dart';

final notificationApiProvider = Provider<NotificationApi>((ref) {
  final client = ref.watch(apiClientProvider);
  return NotificationApi(client);
});

class NotificationNotifier extends StateNotifier<AsyncValue<List<NotificationItem>>> {
  final NotificationApi _api;
  final CacheService _cache;

  NotificationNotifier(this._api, this._cache) : super(const AsyncValue.loading()) {
    loadFromCache();
  }

  void loadFromCache() {
    final cached = _cache.getList<NotificationItem>('notifications', NotificationItem.fromJson);
    if (cached != null) {
      state = AsyncValue.data(cached);
    }
  }

  Future<void> loadNotifications({int page = 1}) async {
    state = const AsyncValue.loading();
    try {
      final result = await _api.getNotifications(page: page);
      _cache.put('notifications', result.notifications.map((n) => n.toJson()).toList());
      state = AsyncValue.data(result.notifications);
    } catch (e) {
      if (e is ApiError) {
        state = AsyncValue.error(e.userMessage, StackTrace.current);
      } else {
        state = AsyncValue.error(e.toString(), StackTrace.current);
      }
    }
  }

  Future<void> markRead(int id) async {
    try {
      await _api.markRead(id);
      await loadNotifications();
    } catch (_) {}
  }

  int get unreadCount {
    return state.valueOrNull?.where((n) => !n.isRead).length ?? 0;
  }
}

final notificationsProvider =
    StateNotifierProvider<NotificationNotifier, AsyncValue<List<NotificationItem>>>((ref) {
  return NotificationNotifier(
    ref.watch(notificationApiProvider),
    ref.watch(cacheServiceProvider),
  );
});

final unreadNotificationCountProvider = Provider<int>((ref) {
  final notifications = ref.watch(notificationsProvider);
  return notifications.valueOrNull?.where((n) => !n.isRead).length ?? 0;
});
