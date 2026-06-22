// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for English (`en`).
class AppLocalizationsEn extends AppLocalizations {
  AppLocalizationsEn([String locale = 'en']) : super(locale);

  @override
  String get appName => 'TheraConnect';

  @override
  String get loginTitle => 'Sign in to your account';

  @override
  String get loginEmailLabel => 'Email';

  @override
  String get loginPasswordLabel => 'Password';

  @override
  String get loginEmailRequired => 'Email is required';

  @override
  String get loginEmailInvalid => 'Enter a valid email';

  @override
  String get loginPasswordRequired => 'Password is required';

  @override
  String get loginButton => 'Sign In';

  @override
  String get loginNoAccountPrompt => 'Don\'t have an account? Sign Up';

  @override
  String get dashboardWelcome => 'Welcome back';

  @override
  String get dashboardAppointmentsTitle => 'Upcoming Appointments';

  @override
  String get dashboardNoAppointments => 'No upcoming appointments';

  @override
  String get dashboardBookAppointment => 'Book Appointment';

  @override
  String get dashboardStatusApproved => 'Approved';

  @override
  String get dashboardStatusPending => 'Pending';

  @override
  String get dashboardNoDate => 'No date';

  @override
  String get chatbotTitle => 'Chatbot';

  @override
  String get chatbotClearTooltip => 'Clear chat';

  @override
  String get chatbotEmptyPrompt => 'Ask me anything about the clinic';

  @override
  String get chatbotEmptyHint => 'Hours, location, appointments, and more';

  @override
  String get chatbotInputHint => 'Type a message...';

  @override
  String get errorGeneric => 'Something went wrong. Please try again.';
}
