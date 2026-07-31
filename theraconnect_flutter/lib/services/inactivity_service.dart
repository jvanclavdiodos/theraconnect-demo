import 'package:shared_preferences/shared_preferences.dart';

class InactivityService {
  InactivityService(this._preferences);

  static const timeout = Duration(minutes: 10);
  static const _lastActivityKey = 'authenticated_last_activity_at';
  static const _persistenceInterval = Duration(seconds: 30);

  final SharedPreferences _preferences;
  DateTime? _lastPersistedAt;

  bool hasTimedOut({DateTime? now}) {
    final timestamp = _preferences.getInt(_lastActivityKey);
    if (timestamp == null) return false;

    return (now ?? DateTime.now())
            .difference(DateTime.fromMillisecondsSinceEpoch(timestamp)) >=
        timeout;
  }

  Future<void> recordActivity({bool force = false, DateTime? now}) async {
    final activityAt = now ?? DateTime.now();
    if (!force &&
        _lastPersistedAt != null &&
        activityAt.difference(_lastPersistedAt!) < _persistenceInterval) {
      return;
    }

    _lastPersistedAt = activityAt;
    await _preferences.setInt(
      _lastActivityKey,
      activityAt.millisecondsSinceEpoch,
    );
  }

  Future<void> clear() async {
    _lastPersistedAt = null;
    await _preferences.remove(_lastActivityKey);
  }
}
