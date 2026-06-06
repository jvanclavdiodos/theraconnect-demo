import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';

class CacheService {
  static const _prefix = 'cache_';
  final SharedPreferences _prefs;

  CacheService(this._prefs);

  Future<void> put(String key, dynamic data) async {
    await _prefs.setString('$_prefix$key', jsonEncode(data));
  }

  T? get<T>(String key, T Function(Map<String, dynamic>) fromJson) {
    final raw = _prefs.getString('$_prefix$key');
    if (raw == null) return null;
    try {
      return fromJson(jsonDecode(raw) as Map<String, dynamic>);
    } catch (_) {
      return null;
    }
  }

  List<T>? getList<T>(String key, T Function(Map<String, dynamic>) fromJson) {
    final raw = _prefs.getString('$_prefix$key');
    if (raw == null) return null;
    try {
      final list = jsonDecode(raw) as List<dynamic>;
      return list
          .map((e) => fromJson(e as Map<String, dynamic>))
          .toList();
    } catch (_) {
      return null;
    }
  }

  Future<void> remove(String key) async {
    await _prefs.remove('$_prefix$key');
  }

  Future<void> clear() async {
    final keys = _prefs.getKeys().where((k) => k.startsWith(_prefix));
    for (final key in keys) {
      await _prefs.remove(key);
    }
  }
}
