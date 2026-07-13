<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RealtimeEventDispatcher
{
    /**
     * Broadcasting is best-effort: persist the transaction first, then queue
     * the event without allowing Reverb or queue failures to break the request.
     */
    public function dispatch(object $event): void
    {
        try {
            DB::afterCommit(function () use ($event) {
                try {
                    event($event);
                } catch (Throwable $exception) {
                    $this->reportFailure($event, $exception);
                }
            });
        } catch (Throwable $exception) {
            $this->reportFailure($event, $exception);
        }
    }

    private function reportFailure(object $event, Throwable $exception): void
    {
        Log::warning('Realtime event dispatch failed', [
            'event' => $event::class,
            'exception' => $exception,
        ]);
    }
}
