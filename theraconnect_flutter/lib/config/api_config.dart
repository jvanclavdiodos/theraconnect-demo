class ApiConfig {
  // Pointed at the Railway production backend (HTTPS, public) — the app works
  // over the phone's own internet from anywhere; no laptop, USB tunnel, or
  // hotspot-IP juggling.
  // Local-dev alternatives:
  //   adb reverse (USB) -> 'http://127.0.0.1:8080/api/v1'  (run: adb reverse tcp:8080 tcp:8080)
  //   LAN (same Wi-Fi)  -> 'http://<PC-LAN-IP>:8080/api/v1'
  //   Android emulator  -> 'http://10.0.2.2:8080/api/v1'
  static const String baseUrl = 'https://theraconnect-demo-production.up.railway.app/api/v1';
  static const Duration connectTimeout = Duration(seconds: 10);
  static const Duration receiveTimeout = Duration(seconds: 15);

  static const String healthEndpoint = '/health';
  static const String registerEndpoint = '/register';
  static const String loginEndpoint = '/login';
  static const String logoutEndpoint = '/logout';
  static const String meEndpoint = '/me';
  static const String profileEndpoint = '/profile';
  static const String cliniciansEndpoint = '/clinicians';
  static const String schedulesEndpoint = '/schedules';
  static const String availabilityEndpoint = '/schedules/availability';
  static const String appointmentsEndpoint = '/appointments';
  static const String conversationsEndpoint = '/conversations';
  static const String notesEndpoint = '/notes';
  static const String assignmentsEndpoint = '/assignments';
  static const String notificationsEndpoint = '/notifications';
  static const String deviceTokenEndpoint = '/device-token';
  static const String chatbotMessageEndpoint = '/chatbot/message';
}
