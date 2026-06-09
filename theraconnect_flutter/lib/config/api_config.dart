class ApiConfig {
  // Production: the Railway HTTPS URL. Replace CHANGEME with your actual
  // Railway domain once it's generated, then rebuild: flutter build apk --release
  // For local testing against docker-compose, use:
  //   Android emulator -> 'http://10.0.2.2:8080/api/v1'
  //   Physical device  -> 'http://<your-PC-LAN-IP>:8080/api/v1'
  static const String baseUrl = 'https://theraconnect-CHANGEME.up.railway.app/api/v1';
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
