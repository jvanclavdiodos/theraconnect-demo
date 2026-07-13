import 'dart:async';

import 'package:dart_pusher_channels/dart_pusher_channels.dart';

import 'api_client.dart';
import 'auth_service.dart';

class RealtimeEvent {
  final String name;
  final Map<String, dynamic> data;

  const RealtimeEvent(this.name, [this.data = const {}]);
}

class RealtimeService {
  final ApiClient _apiClient;
  final AuthService _authService;
  final StreamController<RealtimeEvent> _events =
      StreamController<RealtimeEvent>.broadcast();
  final Set<int> _desiredConversationIds = {};
  final Map<int, PrivateChannel> _conversationChannels = {};
  final Map<int, StreamSubscription<ChannelReadEvent>> _conversationEvents = {};

  PusherChannelsClient? _client;
  StreamSubscription<void>? _connectionSubscription;
  StreamSubscription<ChannelReadEvent>? _notificationSubscription;
  StreamSubscription<ChannelReadEvent>? _appointmentSubscription;
  int? _userId;
  Map<String, dynamic>? _config;

  RealtimeService(this._apiClient, this._authService);

  Stream<RealtimeEvent> get events => _events.stream;

  Future<void> connect(int userId) async {
    if (_client != null && _userId == userId) return;

    try {
      final desiredConversations = Set<int>.of(_desiredConversationIds);
      await disconnect();
      _desiredConversationIds.addAll(desiredConversations);

      final response = await _apiClient.get('/realtime/config');
      final data = Map<String, dynamic>.from(response.data['data'] as Map);
      if (data['enabled'] != true) return;

      final token = await _authService.getToken();
      if (token == null || token.isEmpty) return;

      final options = PusherChannelsOptions.fromHost(
        scheme: data['scheme'] == 'https' ? 'wss' : 'ws',
        host: data['host'] as String,
        key: data['app_key'] as String,
        port: data['port'] as int,
      );
      final authorization = _authorization(data, token);
      final client = PusherChannelsClient.websocket(
        options: options,
        minimumReconnectDelayDuration: const Duration(seconds: 2),
        connectionErrorHandler: (_, __, refresh) => refresh(),
      );
      final userChannel = client.privateChannel(
        'private-users.$userId',
        authorizationDelegate: authorization,
      );

      _client = client;
      _userId = userId;
      _config = data;
      _notificationSubscription =
          userChannel.bind('notification.created').listen(_emit);
      _appointmentSubscription =
          userChannel.bind('appointment.updated').listen(_emit);
      _connectionSubscription = client.onConnectionEstablished.listen((_) {
        userChannel.subscribeIfNotUnsubscribed();
        for (final channel in _conversationChannels.values) {
          channel.subscribeIfNotUnsubscribed();
        }
        unawaited(_ensureDesiredConversations());
        _events.add(const RealtimeEvent('connected'));
      });

      await _ensureDesiredConversations();
      unawaited(client.connect().catchError((_) {}));
    } catch (_) {
      await disconnect();
    }
  }

  Future<void> subscribeConversation(int conversationId) async {
    _desiredConversationIds.add(conversationId);
    await _subscribeConversationNow(conversationId);
  }

  Future<void> _ensureDesiredConversations() async {
    for (final conversationId in _desiredConversationIds) {
      await _subscribeConversationNow(conversationId);
    }
  }

  Future<void> _subscribeConversationNow(int conversationId) async {
    if (_conversationChannels.containsKey(conversationId)) return;

    try {
      final client = _client;
      final token = await _authService.getToken();
      final data = _config;
      if (client == null || token == null || token.isEmpty || data == null) {
        return;
      }

      final channel = client.privateChannel(
        'private-conversations.$conversationId',
        authorizationDelegate: _authorization(data, token),
      );

      _conversationChannels[conversationId] = channel;
      _conversationEvents[conversationId] =
          channel.bind('message.created').listen(_emit);
      channel.subscribeIfNotUnsubscribed();
    } catch (_) {
      return;
    }
  }

  Future<void> unsubscribeConversation(int conversationId) async {
    _desiredConversationIds.remove(conversationId);
    _conversationChannels.remove(conversationId)?.unsubscribe();
    await _conversationEvents.remove(conversationId)?.cancel();
  }

  Future<void> disconnect() async {
    await _connectionSubscription?.cancel();
    await _notificationSubscription?.cancel();
    await _appointmentSubscription?.cancel();
    for (final subscription in _conversationEvents.values) {
      await subscription.cancel();
    }
    _conversationEvents.clear();
    _conversationChannels.clear();
    _desiredConversationIds.clear();

    final client = _client;
    _client = null;
    _userId = null;
    _config = null;
    if (client != null) {
      await client.disconnect();
      client.dispose();
    }
  }

  EndpointAuthorizableChannelTokenAuthorizationDelegate<
      PrivateChannelAuthorizationData> _authorization(
    Map<String, dynamic> data,
    String token,
  ) {
    return EndpointAuthorizableChannelTokenAuthorizationDelegate
        .forPrivateChannel(
      authorizationEndpoint: Uri.parse(data['auth_endpoint'] as String),
      headers: {
        'Authorization': 'Bearer $token',
        'Accept': 'application/json',
      },
    );
  }

  void _emit(ChannelReadEvent event) {
    _events.add(RealtimeEvent(event.name, event.tryGetDataAsMap() ?? const {}));
  }
}
