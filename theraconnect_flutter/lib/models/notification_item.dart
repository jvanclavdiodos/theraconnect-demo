import 'dart:convert';

class NotificationItem {
  final int id;
  final String type;
  final String title;
  final String body;
  final Map<String, dynamic>? data;
  final String? readAt;
  final String? sentAt;
  final String? createdAt;

  const NotificationItem({
    required this.id,
    required this.type,
    required this.title,
    required this.body,
    this.data,
    this.readAt,
    this.sentAt,
    this.createdAt,
  });

  factory NotificationItem.fromJson(Map<String, dynamic> json) {
    return NotificationItem(
      id: json['id'] as int,
      type: json['type'] as String,
      title: json['title'] as String,
      body: json['body'] as String,
      data: _parseData(json['data']),
      readAt: json['read_at'] as String?,
      sentAt: json['sent_at'] as String?,
      createdAt: json['created_at'] as String?,
    );
  }

  /// The API's `data` is normally a JSON object, but some rows historically
  /// store it as a JSON-encoded *string* (double-encoded). Accept both — and
  /// anything else (empty/list/garbage) degrades to null — so a single
  /// malformed row never blanks the whole notifications screen.
  static Map<String, dynamic>? _parseData(dynamic raw) {
    if (raw == null) return null;
    if (raw is Map) return Map<String, dynamic>.from(raw);
    if (raw is String) {
      if (raw.isEmpty) return null;
      try {
        final decoded = jsonDecode(raw);
        return decoded is Map ? Map<String, dynamic>.from(decoded) : null;
      } catch (_) {
        return null;
      }
    }
    return null;
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'type': type,
      'title': title,
      'body': body,
      'data': data,
      'read_at': readAt,
      'sent_at': sentAt,
      'created_at': createdAt,
    };
  }

  bool get isRead => readAt != null;
}
