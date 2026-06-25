import 'package:flutter/material.dart';
import '../theme/app_theme.dart';
import '../utils/validators.dart';

/// A password input with a show/hide toggle plus a live strength meter and
/// requirements checklist that update as the user types. The meter/checklist
/// appear only once the field is non-empty.
class PasswordField extends StatefulWidget {
  final TextEditingController controller;
  final String label;
  final bool showMeter;
  final bool autofocus;
  final TextInputAction textInputAction;
  final void Function(String)? onFieldSubmitted;

  /// Override the default validator (defaults to [Validators.passwordError]).
  final String? Function(String?)? validator;

  const PasswordField({
    super.key,
    required this.controller,
    this.label = 'Password',
    this.showMeter = true,
    this.autofocus = false,
    this.textInputAction = TextInputAction.next,
    this.onFieldSubmitted,
    this.validator,
  });

  @override
  State<PasswordField> createState() => _PasswordFieldState();
}

class _PasswordFieldState extends State<PasswordField> {
  bool _obscure = true;

  @override
  void initState() {
    super.initState();
    widget.controller.addListener(_onChanged);
  }

  @override
  void dispose() {
    widget.controller.removeListener(_onChanged);
    super.dispose();
  }

  void _onChanged() {
    if (mounted) setState(() {});
  }

  Widget _requirement(bool ok, String text) {
    final color = ok ? AppTheme.green : AppTheme.slateLight;
    return Padding(
      padding: const EdgeInsets.only(top: 3),
      child: Row(
        children: [
          Icon(ok ? Icons.check_circle : Icons.circle_outlined, size: 16, color: color),
          const SizedBox(width: 6),
          Expanded(child: Text(text, style: TextStyle(fontSize: 12.5, color: color))),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final value = widget.controller.text;
    final strength = Validators.passwordStrength(value);
    final color = switch (strength.level) {
      PasswordStrengthLevel.weak => AppTheme.red,
      PasswordStrengthLevel.medium => AppTheme.amber,
      PasswordStrengthLevel.strong => AppTheme.green,
    };

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        TextFormField(
          controller: widget.controller,
          obscureText: _obscure,
          autofocus: widget.autofocus,
          textInputAction: widget.textInputAction,
          onFieldSubmitted: widget.onFieldSubmitted,
          decoration: InputDecoration(
            labelText: widget.label,
            prefixIcon: const Icon(Icons.lock),
            border: const OutlineInputBorder(),
            suffixIcon: IconButton(
              icon: Icon(_obscure ? Icons.visibility : Icons.visibility_off),
              tooltip: _obscure ? 'Show password' : 'Hide password',
              onPressed: () => setState(() => _obscure = !_obscure),
            ),
          ),
          validator: widget.validator ?? Validators.passwordError,
        ),
        if (widget.showMeter && value.isNotEmpty) ...[
          const SizedBox(height: 8),
          ClipRRect(
            borderRadius: BorderRadius.circular(4),
            child: LinearProgressIndicator(
              value: strength.fraction,
              minHeight: 6,
              backgroundColor: AppTheme.neutral200,
              valueColor: AlwaysStoppedAnimation<Color>(color),
            ),
          ),
          const SizedBox(height: 4),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text('Password strength',
                  style: TextStyle(fontSize: 12, color: AppTheme.slateLight)),
              Text(strength.label,
                  style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: color)),
            ],
          ),
          const SizedBox(height: 4),
          _requirement(Validators.reqLength(value), '8–20 characters'),
          _requirement(Validators.reqUppercase(value), 'At least one uppercase letter'),
          _requirement(Validators.reqDigit(value), 'At least one number'),
          _requirement(Validators.reqNoSpace(value), 'No spaces'),
        ],
      ],
    );
  }
}
