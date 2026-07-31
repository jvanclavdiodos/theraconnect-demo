@php
    $isActive = fn(string $route) => request()->routeIs($route) ? 'active' : '';
    $role = auth()->check() ? auth()->user()->role : null;

    $msgUnread = 0;
    $assignmentActivity = 0;
    if ($role === 'clinician' && auth()->user()->clinician) {
        $msgUnread = app(\App\Services\MessageService::class)
            ->clinicianUnreadCount(auth()->user()->clinician->id, auth()->id());
        $assignmentActivity = app(\App\Services\AssignmentBadgeService::class)
            ->clinicianPendingReviewCount(auth()->user()->clinician->id);
    }
@endphp

<aside class="text-white" id="sidebar-wrapper" role="navigation" aria-label="Main navigation" :class="{ 'open': sidebarOpen }">
    <div class="sidebar-heading d-flex justify-content-between align-items-center">
        <div class="tc-brand">
            <span class="tc-logo"><i class="bi bi-activity"></i></span>
            <div>
                <div class="tc-brand-name">{{ config('app.name', 'TheraConnect') }}</div>
                <div class="tc-brand-sub">Clinic Management</div>
            </div>
        </div>
        <button class="btn btn-sm btn-outline-light d-md-none" @click="sidebarOpen = false">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <nav>
        @auth
            {{-- Overview --}}
            <div class="tc-nav-group">
                <div class="tc-nav-group-label">Overview</div>
                <a href="{{ route('dashboard') }}" class="tc-nav-item {{ $isActive('dashboard') }}">
                    <i class="bi bi-grid-1x2"></i> <span>Dashboard</span>
                    <i class="bi bi-chevron-right tc-nav-chevron"></i>
                </a>
                @php
                    $notifUnread = \App\Models\Notification::where('user_id', auth()->id())
                        ->unreadGeneral()->count();
                @endphp
                <a href="{{ route('notifications.index') }}" class="tc-nav-item {{ $isActive('notifications.index') }}">
                    <i class="bi bi-bell"></i> <span>Notifications</span>
                    <span data-realtime-notification-count data-count="{{ $notifUnread }}"
                          class="badge bg-primary rounded-pill ms-auto {{ $notifUnread > 0 ? '' : 'd-none' }}">
                        {{ $notifUnread > 9 ? '9+' : $notifUnread }}
                    </span>
                    <i class="bi bi-chevron-right tc-nav-chevron {{ $notifUnread > 0 ? 'd-none' : '' }}"></i>
                </a>
            </div>

            @if(in_array($role, ['admin', 'clinician']))
                {{-- Clinical --}}
                <div class="tc-nav-group">
                    <div class="tc-nav-group-label">Clinical</div>
                    <a href="{{ route('appointments.index') }}" class="tc-nav-item {{ $isActive('appointments.*') }}">
                        <i class="bi bi-calendar-check"></i> <span>Appointments</span>
                        <i class="bi bi-chevron-right tc-nav-chevron"></i>
                    </a>
                    <a href="{{ route('assignments.index') }}" class="tc-nav-item {{ $isActive('assignments.*') }}">
                        <i class="bi bi-clipboard-check"></i> <span>Assignments</span>
                        <span data-assignment-activity-count data-count="{{ $assignmentActivity }}"
                              class="badge bg-primary rounded-pill ms-auto {{ $assignmentActivity > 0 ? '' : 'd-none' }}"
                              aria-label="{{ $assignmentActivity }} submissions awaiting review">
                            {{ $assignmentActivity > 9 ? '9+' : $assignmentActivity }}
                        </span>
                        <i class="bi bi-chevron-right tc-nav-chevron {{ $assignmentActivity > 0 ? 'd-none' : '' }}"></i>
                    </a>
                    <a href="{{ route('patients.index') }}" class="tc-nav-item {{ $isActive('patients.*') }}">
                        <i class="bi bi-people"></i> <span>Patients</span>
                        <i class="bi bi-chevron-right tc-nav-chevron"></i>
                    </a>
                    @if($role === 'clinician')
                        <a href="{{ route('guide.show') }}" class="tc-nav-item {{ $isActive('guide.show') }}">
                            <i class="bi bi-question-circle"></i> <span>User Guide</span>
                            <i class="bi bi-chevron-right tc-nav-chevron"></i>
                        </a>
                        <a href="{{ route('messages.index') }}" class="tc-nav-item {{ $isActive('messages.*') }}">
                            <i class="bi bi-chat-dots"></i> <span>Messages</span>
                            <span data-realtime-message-count data-count="{{ $msgUnread }}"
                                  class="badge bg-primary rounded-pill ms-auto {{ $msgUnread > 0 ? '' : 'd-none' }}">
                                {{ $msgUnread > 9 ? '9+' : $msgUnread }}
                            </span>
                            <i class="bi bi-chevron-right tc-nav-chevron {{ $msgUnread > 0 ? 'd-none' : '' }}"></i>
                        </a>
                    @endif
                </div>

                {{-- Tools — clinic administration (admin only) --}}
                @if($role === 'admin')
                    <div class="tc-nav-group">
                        <div class="tc-nav-group-label">Administration</div>
                        <a href="{{ route('clinicians.index') }}" class="tc-nav-item {{ $isActive('clinicians.*') }}">
                            <i class="bi bi-person-badge"></i> <span>Clinicians</span>
                            <i class="bi bi-chevron-right tc-nav-chevron"></i>
                        </a>
                        <a href="{{ route('chatbot-content.index') }}" class="tc-nav-item {{ $isActive('chatbot-content.*') }}">
                            <i class="bi bi-robot"></i> <span>Chatbot Content</span>
                            <i class="bi bi-chevron-right tc-nav-chevron"></i>
                        </a>
                        <a href="{{ route('notifications.logs') }}" class="tc-nav-item {{ $isActive('notifications.logs') }}">
                            <i class="bi bi-bell"></i> <span>Notification Logs</span>
                            <i class="bi bi-chevron-right tc-nav-chevron"></i>
                        </a>
                        <a href="{{ route('activity-logs.index') }}" class="tc-nav-item {{ $isActive('activity-logs.*') }}">
                            <i class="bi bi-journal-text"></i> <span>Activity Audit</span>
                            <i class="bi bi-chevron-right tc-nav-chevron"></i>
                        </a>
                    </div>
                @endif
            @endif
        @endauth
    </nav>

</aside>
