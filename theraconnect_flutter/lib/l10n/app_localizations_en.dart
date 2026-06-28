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
  String get chatbotTitle => 'Joy';

  @override
  String get chatbotClearTooltip => 'Clear chat';

  @override
  String get chatbotEmptyPrompt => 'Hi, I\'m Joy!';

  @override
  String get chatbotEmptyHint =>
      'Your TheraConnect assistant — ask me about appointments, assignments, and clinic info.';

  @override
  String get chatbotInputHint => 'Message Joy...';

  @override
  String get errorGeneric => 'Something went wrong. Please try again.';
}
