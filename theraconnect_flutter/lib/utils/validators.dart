/// Password rules + strength scoring for the mobile app.
///
/// These MUST stay in sync with the backend (`app/Rules/StrongPassword.php`) and
/// the web component (`resources/views/partials/password-strength.blade.php`):
/// hard rules = 8–20 chars, ≥1 uppercase, ≥1 digit, no spaces; the strength
/// score additionally rewards length, lowercase, and special characters.
library;

enum PasswordStrengthLevel { weak, medium, strong }

class PasswordStrength {
  final PasswordStrengthLevel level;
  final String label;

  /// Progress-bar fill, 0..1.
  final double fraction;

  const PasswordStrength(this.level, this.label, this.fraction);
}

class Validators {
  static const int passwordMin = 8;
  static const int passwordMax = 20;

  static final RegExp _upper = RegExp(r'[A-Z]');
  static final RegExp _lower = RegExp(r'[a-z]');
  static final RegExp _digit = RegExp(r'[0-9]');
  static final RegExp _space = RegExp(r'\s');
  static final RegExp _special = RegExp(r'[^A-Za-z0-9]');

  /// The first unmet hard requirement, or null when the password is valid.
  /// Used as a `TextFormField` validator (runs on submit).
  static String? passwordError(String? value) {
    final v = value ?? '';
    if (v.isEmpty) return 'Password is required';
    if (v.length < passwordMin || v.length > passwordMax) {
      return 'Password must be between $passwordMin and $passwordMax characters';
    }
    if (!_upper.hasMatch(v)) return 'Add at least one uppercase letter';
    if (!_digit.hasMatch(v)) return 'Add at least one number';
    if (_space.hasMatch(v)) return 'Remove spaces from your password';
    return null;
  }

  // Individual requirement checks (drive the live checklist).
  static bool reqLength(String v) => v.length >= passwordMin && v.length <= passwordMax;
  static bool reqUppercase(String v) => _upper.hasMatch(v);
  static bool reqDigit(String v) => _digit.hasMatch(v);
  static bool reqNoSpace(String v) => v.isNotEmpty && !_space.hasMatch(v);
  static bool isStrongEnough(String v) =>
      reqLength(v) && reqUppercase(v) && reqDigit(v) && reqNoSpace(v);

  static PasswordStrength passwordStrength(String v) {
    var s = 0;
    if (v.length >= 8) s++;
    if (v.length >= 12) s++;
    if (_lower.hasMatch(v)) s++;
    if (_upper.hasMatch(v)) s++;
    if (_digit.hasMatch(v)) s++;
    if (_special.hasMatch(v)) s++;

    if (s <= 2) return const PasswordStrength(PasswordStrengthLevel.weak, 'Weak', 0.33);
    if (s <= 4) return const PasswordStrength(PasswordStrengthLevel.medium, 'Medium', 0.66);
    return const PasswordStrength(PasswordStrengthLevel.strong, 'Strong', 1.0);
  }
}
