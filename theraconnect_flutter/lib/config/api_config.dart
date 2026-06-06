class ApiConfig {
  static const String baseUrl = 'http://10.0.2.2:8080/api/v1';
  static const Duration connectTimeout = Duration(seconds: 10);
  static const Duration receiveTimeout = Duration(seconds: 15);

  static const String healthEndpoint = '/health';
  static const String registerEndpoint = '/register';
  static const String loginEndpoint = '/login';
  static const String logoutEndpoint = '/logout';
  static const String meEndpoint = '/me';
  static const String profileEndpoint = '/profile';
  static const String schedulesEndpoint = '/schedules';
  static const String appointmentsEndpoint = '/appointments';
  static const String assignmentsEndpoint = '/assignments';
  static const String notificationsEndpoint = '/notifications';
  static const String deviceTokenEndpoint = '/device-token';
  static const String chatbotMessageEndpoint = '/chatbot/message';
}
