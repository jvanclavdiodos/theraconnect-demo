import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../models/patient.dart';
import '../../providers/auth_provider.dart';
import '../../widgets/password_field.dart';

class RegisterScreen extends ConsumerStatefulWidget {
  const RegisterScreen({super.key});

  @override
  ConsumerState<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends ConsumerState<RegisterScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  final _confirmPasswordController = TextEditingController();
  final _contactController = TextEditingController();
  final _personalIssuesController = TextEditingController();
  String? _gender;
  String? _education;
  String? _employment;
  bool _obscureConfirm = true;
  bool _acceptedTerms = false;

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    _passwordController.dispose();
    _confirmPasswordController.dispose();
    _contactController.dispose();
    _personalIssuesController.dispose();
    super.dispose();
  }

  DropdownMenuItem<String> _item(String v) =>
      DropdownMenuItem(value: v, child: Text(v));

  String? _trimOrNull(TextEditingController c) =>
      c.text.trim().isEmpty ? null : c.text.trim();

  Future<void> _showTerms() async {
    final accepted = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('User Agreement and Privacy Notice'),
        content: const SizedBox(
          width: 560,
          child: SingleChildScrollView(
            child: _TermsContent(),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Close'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('I Agree'),
          ),
        ],
      ),
    );

    if (accepted == true && mounted) {
      setState(() => _acceptedTerms = true);
    }
  }

  Future<void> _register() async {
    if (!_acceptedTerms) {
      await _showTerms();
      if (!_acceptedTerms) return;
    }

    if (!_formKey.currentState!.validate()) return;
    final colorScheme = Theme.of(context).colorScheme;

    final error = await ref.read(authProvider.notifier).register(
          _nameController.text.trim(),
          _emailController.text.trim(),
          _passwordController.text,
          _confirmPasswordController.text,
          acceptedTerms: _acceptedTerms,
          contactNo: _trimOrNull(_contactController),
          gender: _gender,
          educationalAttainment: _education,
          employmentStatus: _employment,
          personalIssues: _trimOrNull(_personalIssuesController),
        );

    if (error != null && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(error), backgroundColor: colorScheme.error),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final authState = ref.watch(authProvider);

    ref.listen(authProvider, (_, next) {
      if (next.status == AuthState.authenticated && mounted) {
        context.go('/dashboard');
      }
    });

    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: Form(
              key: _formKey,
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Icon(Icons.person_add,
                      size: 64, color: Theme.of(context).colorScheme.primary),
                  const SizedBox(height: 16),
                  Text(
                    'Create Account',
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                          fontWeight: FontWeight.bold,
                        ),
                  ),
                  const SizedBox(height: 32),
                  if (authState.error != null)
                    Container(
                      padding: const EdgeInsets.all(12),
                      margin: const EdgeInsets.only(bottom: 16),
                      decoration: BoxDecoration(
                        color: Theme.of(context).colorScheme.errorContainer,
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Text(
                        authState.error!,
                        style: TextStyle(
                            color:
                                Theme.of(context).colorScheme.onErrorContainer),
                      ),
                    ),
                  TextFormField(
                    controller: _nameController,
                    textInputAction: TextInputAction.next,
                    decoration: const InputDecoration(
                      labelText: 'Full Name',
                      prefixIcon: Icon(Icons.person),
                      border: OutlineInputBorder(),
                    ),
                    validator: (v) => v == null || v.trim().isEmpty
                        ? 'Name is required'
                        : null,
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: _emailController,
                    keyboardType: TextInputType.emailAddress,
                    textInputAction: TextInputAction.next,
                    decoration: const InputDecoration(
                      labelText: 'Email',
                      prefixIcon: Icon(Icons.email),
                      border: OutlineInputBorder(),
                    ),
                    validator: (v) {
                      if (v == null || v.trim().isEmpty)
                        return 'Email is required';
                      if (!v.contains('@')) return 'Enter a valid email';
                      return null;
                    },
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: _contactController,
                    keyboardType: TextInputType.phone,
                    textInputAction: TextInputAction.next,
                    decoration: const InputDecoration(
                      labelText: 'Contact Number (optional)',
                      prefixIcon: Icon(Icons.phone),
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 24),
                  Align(
                    alignment: Alignment.centerLeft,
                    child: Text('About you (optional)',
                        style: Theme.of(context).textTheme.labelLarge?.copyWith(
                              color: Theme.of(context)
                                  .colorScheme
                                  .onSurfaceVariant,
                            )),
                  ),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<String>(
                    initialValue: _gender,
                    isExpanded: true,
                    decoration: const InputDecoration(
                      labelText: 'Gender',
                      prefixIcon: Icon(Icons.wc),
                      border: OutlineInputBorder(),
                    ),
                    items: Patient.genders.map(_item).toList(),
                    onChanged: (v) => setState(() => _gender = v),
                  ),
                  const SizedBox(height: 16),
                  DropdownButtonFormField<String>(
                    initialValue: _education,
                    isExpanded: true,
                    decoration: const InputDecoration(
                      labelText: 'Educational Attainment',
                      prefixIcon: Icon(Icons.school),
                      border: OutlineInputBorder(),
                    ),
                    items: Patient.educationLevels.map(_item).toList(),
                    onChanged: (v) => setState(() => _education = v),
                  ),
                  const SizedBox(height: 16),
                  DropdownButtonFormField<String>(
                    initialValue: _employment,
                    isExpanded: true,
                    decoration: const InputDecoration(
                      labelText: 'Employment Status',
                      prefixIcon: Icon(Icons.work_outline),
                      border: OutlineInputBorder(),
                    ),
                    items: Patient.employmentStatuses.map(_item).toList(),
                    onChanged: (v) => setState(() => _employment = v),
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: _personalIssuesController,
                    maxLines: 3,
                    maxLength: 2000,
                    textInputAction: TextInputAction.newline,
                    decoration: const InputDecoration(
                      labelText: 'What brings you here? (optional)',
                      prefixIcon: Icon(Icons.favorite_border),
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 16),
                  PasswordField(
                    controller: _passwordController,
                    label: 'Password',
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: _confirmPasswordController,
                    obscureText: _obscureConfirm,
                    textInputAction: TextInputAction.done,
                    decoration: InputDecoration(
                      labelText: 'Confirm Password',
                      prefixIcon: const Icon(Icons.lock_outline),
                      border: const OutlineInputBorder(),
                      suffixIcon: IconButton(
                        icon: Icon(_obscureConfirm
                            ? Icons.visibility
                            : Icons.visibility_off),
                        onPressed: () =>
                            setState(() => _obscureConfirm = !_obscureConfirm),
                      ),
                    ),
                    validator: (v) {
                      if (v != _passwordController.text)
                        return 'Passwords do not match';
                      return null;
                    },
                    onFieldSubmitted: (_) => _register(),
                  ),
                  const SizedBox(height: 16),
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Checkbox(
                        value: _acceptedTerms,
                        onChanged: (value) {
                          if (value == false) {
                            setState(() => _acceptedTerms = false);
                            return;
                          }
                          _showTerms();
                        },
                      ),
                      Expanded(
                        child: Padding(
                          padding: const EdgeInsets.only(top: 10),
                          child: Wrap(
                            crossAxisAlignment: WrapCrossAlignment.center,
                            children: [
                              const Text(
                                  'By creating an account, I agree to the '),
                              TextButton(
                                onPressed: _showTerms,
                                style: TextButton.styleFrom(
                                  minimumSize: Size.zero,
                                  padding: EdgeInsets.zero,
                                  tapTargetSize:
                                      MaterialTapTargetSize.shrinkWrap,
                                ),
                                child: const Text(
                                  'TheraConnect User Agreement and Privacy Notice',
                                ),
                              ),
                              const Text(
                                ', including the processing of health information as described.',
                              ),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 24),
                  FilledButton(
                    onPressed:
                        authState.status == AuthState.loading || !_acceptedTerms
                            ? null
                            : _register,
                    style: FilledButton.styleFrom(
                      minimumSize: const Size(double.infinity, 48),
                    ),
                    child: authState.status == AuthState.loading
                        ? SizedBox(
                            height: 20,
                            width: 20,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: Theme.of(context).colorScheme.onPrimary,
                            ),
                          )
                        : const Text('Create Account'),
                  ),
                  const SizedBox(height: 16),
                  TextButton(
                    onPressed: () => context.go('/login'),
                    child: const Text('Already have an account? Sign In'),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _TermsContent extends StatelessWidget {
  const _TermsContent();

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    Widget section(String title, String body) => Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title, style: theme.textTheme.titleSmall),
              const SizedBox(height: 4),
              Text(body),
            ],
          ),
        );

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('Effective date: July 17, 2026',
            style: theme.textTheme.labelLarge),
        const SizedBox(height: 12),
        const Text(
          'These Terms and Conditions govern your use of TheraConnect, the clinic\'s web and mobile platform for patient accounts, appointment management, assessments, assignments, and related communications.',
        ),
        const SizedBox(height: 12),
        section('1. Purpose of TheraConnect',
            'TheraConnect helps you communicate with the clinic and manage care-related administrative activities. It is not an emergency service and does not replace professional medical advice, diagnosis, treatment, or crisis support. If you believe you or another person is in immediate danger, contact local emergency services or an appropriate crisis service.'),
        section('2. Eligibility and account information',
            'You must be legally able to agree to these terms. If you are registering on behalf of a minor or another person, you confirm that you are authorized to do so. Keep your password confidential and provide accurate account information.'),
        section('3. Appointments and clinical care',
            'Appointment requests are subject to clinician availability and confirmation. The clinic may reschedule or cancel appointments when necessary. Your clinician remains responsible for clinical decisions; using TheraConnect does not guarantee a particular outcome or availability.'),
        section('4. Philippine Data Privacy Act and your information',
            'The clinic operating your TheraConnect account is the Personal Information Controller for personal data whose processing it determines. It processes personal data in accordance with Republic Act No. 10173, the Data Privacy Act of 2012, its Implementing Rules and Regulations, and applicable National Privacy Commission issuances. Processing must follow transparency, legitimate purpose, and proportionality.'),
        section('Information processed',
            'Depending on how you use TheraConnect, this may include identity and contact details; demographic and profile information; account, device, and security data; appointments and attendance; clinician relationships; messages; personal concerns; assessments and responses; mood logs; goals; shared clinical notes; assignments, submissions, uploaded files, notifications, and technical or audit logs. Health, education, and care information may be sensitive personal information under Philippine law.'),
        section('Purposes and lawful processing',
            'Data is processed only as reasonably necessary to create and secure your account, coordinate appointments and care, communicate with assigned clinicians, administer care-related platform features, send service notices, maintain clinical and security records, prevent misuse, protect life and health, respond to requests, and comply with legal duties. Processing may rely on consent when required, medical-treatment purposes, requested services, legal obligations, protection of life or health, or another basis permitted by law. This is not consent to unrelated advertising.'),
        section('Access, disclosure, and providers',
            'Data may be accessed by authorized clinic personnel and clinicians involved in your care, and by contracted providers supporting hosting, storage, email, push notifications, video meetings, security, and platform operations. It may also be disclosed when you authorize it, during an emergency, or when required by law. Providers should receive only necessary data and use appropriate safeguards. Some providers may process data outside the Philippines subject to applicable protections.'),
        section('Retention and security',
            'Personal data will be retained only as long as necessary for the stated purposes, clinical-record requirements, legitimate business needs, legal claims, or periods required by law, then securely deleted, anonymized, or disposed of when permitted. Reasonable organizational, physical, and technical safeguards are used, but no internet service can guarantee absolute security.'),
        section('Your data-subject rights',
            'Subject to lawful limitations, you have rights to be informed, access your data, object to processing, correct inaccurate or incomplete data, request erasure or blocking, obtain data portability where applicable, file a complaint with the National Privacy Commission, and claim damages when legally available. Where processing is based on consent, you may withdraw it prospectively by contacting the clinic or its Data Protection Officer. Other lawful processing or mandatory retention may continue.'),
        section('Privacy requests and incidents',
            'Contact the clinic\'s Data Protection Officer or privacy contact to exercise a right, withdraw consent, ask a privacy question, or report suspected unauthorized access. The clinic may verify your identity before acting. Qualifying personal data breaches will be reported to affected data subjects and the National Privacy Commission as required by law.'),
        section('5. Notifications and communications',
            'You may receive in-app, push, or email notifications about appointments, assessments, assignments, and account activity. Notifications can be delayed or unavailable, so check your account directly for important updates.'),
        section('6. Acceptable use',
            'Use TheraConnect lawfully and respectfully. Do not access another person\'s account, interfere with the service, upload harmful material, or harass, threaten, or impersonate anyone. The clinic may restrict access to protect patients, staff, or the service.'),
        section('7. Changes and contact',
            'The clinic may update these terms and this privacy notice as the service or legal requirements change. Material updates will be presented in the platform when practical and may require renewed agreement. For questions about your account, care, or these terms, contact the clinic directly.'),
        const Text(
          'By selecting I Agree, you confirm that you have read and agree to these terms, acknowledge this privacy notice, and, where consent is the applicable lawful basis, consent to the described processing. These terms supplement any more specific clinic consent form or privacy notice provided to you.',
        ),
      ],
    );
  }
}
