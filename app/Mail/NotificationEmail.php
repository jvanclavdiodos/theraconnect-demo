<?php

namespace App\Mail;

use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotificationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Notification $notification,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->notification->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notification',
            with: [
                'notification' => $this->notification,
                'ctaUrl' => $this->ctaUrl(),
            ],
        );
    }

    private function ctaUrl(): string
    {
        $user = $this->notification->user;

        if ($user?->role === 'patient') {
            return match ($this->notification->type) {
                'assignment_created', 'assignment_deadline' => route('portal.assignments.index'),
                'assessment_assigned' => route('portal.assessments.index'),
                default => route('portal.appointments.index'),
            };
        }

        return match ($this->notification->type) {
            'patient_request' => route('patients.index'),
            default => route('appointments.index'),
        };
    }
}
