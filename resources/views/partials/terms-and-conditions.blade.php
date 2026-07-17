@php
    $privacyController = config('app.privacy_controller_name', config('app.name', 'TheraConnect'));
    $privacyEmail = config('app.privacy_contact_email');
@endphp

<p><strong>Effective date: July 17, 2026</strong></p>

<p>These Terms and Conditions govern your use of TheraConnect, the clinic's web and mobile platform for patient accounts, appointment management, assessments, assignments, and related communications. By creating an account, you confirm that you have read and agree to these terms.</p>

<h6>1. Purpose of TheraConnect</h6>
<p>TheraConnect helps you communicate with the clinic and manage care-related administrative activities. It is not an emergency service and does not replace professional medical advice, diagnosis, treatment, or crisis support. If you believe you or another person is in immediate danger, contact local emergency services or an appropriate crisis service.</p>

<h6>2. Eligibility and account information</h6>
<p>You must be legally able to agree to these terms. If you are registering on behalf of a minor or another person, you confirm that you are authorized to do so. You must provide accurate information, keep your password confidential, and promptly tell the clinic if you believe your account has been accessed without permission.</p>

<h6>3. Appointments and clinical care</h6>
<p>Appointment requests are subject to clinician availability and confirmation. The platform may show appointment details, reminders, and changes, but the clinic may reschedule or cancel appointments when necessary. Your clinician remains responsible for clinical decisions; using TheraConnect does not guarantee a particular outcome or availability.</p>

<h6>4. Philippine Data Privacy Act and your information</h6>
<p><strong>{{ $privacyController }}</strong>, the clinic operating your TheraConnect account, is the Personal Information Controller for personal data whose processing it determines. It processes personal data in accordance with Republic Act No. 10173, the Data Privacy Act of 2012, its Implementing Rules and Regulations, and applicable National Privacy Commission issuances. Processing must follow transparency, legitimate purpose, and proportionality.</p>

<p><strong>Information processed.</strong> Depending on how you use TheraConnect, this may include your identity and contact details; demographic and profile information; account, device, and security data; appointment and attendance records; clinician relationships; messages; personal concerns; assessments and responses; mood logs; therapy goals; clinical notes shared through the platform; assignments, submissions, and uploaded files; notification history; and technical or audit logs. Health, education, and related care information may be sensitive personal information under Philippine law. Do not provide another person's information unless you are authorized to do so.</p>

<p><strong>Purposes and lawful processing.</strong> Data is processed only as reasonably necessary to create and secure your account; verify identity and authority; coordinate appointments and care; enable communication with assigned clinicians; administer assessments, assignments, goals, notes, and submissions; send service and care-related notices; maintain clinical, security, and audit records; prevent misuse; respond to support or privacy requests; protect life and health where necessary; and comply with legal or regulatory duties. Processing may rely on your consent when required, medical-treatment purposes, steps necessary to provide requested services, legal obligations, protection of life or health, or another basis permitted by law. Agreement to this notice is not consent to unrelated advertising.</p>

<p><strong>Access, disclosure, and service providers.</strong> Data may be accessed only by authorized clinic personnel and clinicians involved in your care, and by contracted providers that support hosting, storage, email, push notifications, video meetings, security, and other platform operations. Information may also be disclosed when you direct or authorize it, when needed for an emergency, or when required by law or a competent authority. Providers must receive only data reasonably necessary for their function and be subject to appropriate privacy and security safeguards. Some providers may process data outside the Philippines, subject to applicable contractual and legal protections.</p>

<p><strong>Retention and security.</strong> Personal data will be retained only for as long as necessary for the stated purposes, applicable clinical-record requirements, legitimate business needs, legal claims, or other periods required by law, then securely deleted, anonymized, or otherwise disposed of when permitted. The clinic and its providers must use reasonable organizational, physical, and technical safeguards designed to protect confidentiality, integrity, and availability. No internet service can guarantee absolute security.</p>

<p><strong>Your data-subject rights.</strong> Subject to lawful limitations, you may exercise the rights to be informed, access your data, object to processing, correct inaccurate or incomplete data, request erasure or blocking, obtain data portability where applicable, file a complaint with the National Privacy Commission, and claim damages when legally available. Where processing is based on consent, you may withdraw that consent prospectively; withdrawal does not invalidate prior lawful processing and may limit features that require the affected data. The clinic may retain or continue processing information when another lawful basis or mandatory retention requirement applies.</p>

<p><strong>Privacy requests and incidents.</strong> To exercise a right, withdraw consent, ask a privacy question, or report suspected unauthorized access, contact the clinic's Data Protection Officer or privacy contact. @if($privacyEmail)You may email <a href="mailto:{{ $privacyEmail }}">{{ $privacyEmail }}</a>. @endif The clinic may reasonably verify your identity before acting on a request. Qualifying personal data breaches will be reported to affected data subjects and the National Privacy Commission as required by law.</p>

<h6>5. Notifications and communications</h6>
<p>You may receive in-app, push, or email notifications about appointments, assessments, assignments, and account activity. Notifications can be delayed or unavailable because of device, network, or email-provider issues. Check your account directly for important updates and keep your contact details current.</p>

<h6>6. Acceptable use</h6>
<p>You must use TheraConnect lawfully and respectfully. Do not attempt to access another person's account, interfere with the service, upload harmful material, or use the platform to harass, threaten, or impersonate anyone. The clinic may restrict or suspend access to protect patients, staff, or the service.</p>

<h6>7. Changes and contact</h6>
<p>The clinic may update these terms and this privacy notice as the service or legal requirements change. Material updates will be presented in the platform when practical and may require renewed agreement. For questions about your account, care, or these terms, contact the clinic directly.</p>

<p class="mb-0">By selecting <strong>I Agree</strong>, you confirm that you have read and agree to these terms, acknowledge this privacy notice, and, where consent is the applicable lawful basis, consent to the described processing. These terms supplement, and do not replace, any more specific clinic consent form or privacy notice provided to you.</p>
