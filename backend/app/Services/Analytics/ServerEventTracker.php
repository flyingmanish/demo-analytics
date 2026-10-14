<?php

namespace App\Services\Analytics;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ServerEventTracker
{
    public function track(
        string $eventName,
        string $visitorId,
        string $sessionId,
        array $properties = [],
        ?string $pagePath = null,
    ): string {
        $event = Validator::make([
            'event_name' => $eventName,
            'visitor_id' => $visitorId,
            'session_id' => $sessionId,
            'page_path' => $pagePath,
        ], [
            'event_name' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{0,63}$/'],
            'visitor_id' => ['required', 'string', 'regex:/^[a-zA-Z0-9_-]{1,128}$/'],
            'session_id' => ['required', 'string', 'regex:/^[a-zA-Z0-9_-]{1,128}$/'],
            'page_path' => ['nullable', 'string', 'max:512', 'regex:/^\//'],
        ])->validate();

        $serializedProperties = json_encode((object) $properties, JSON_THROW_ON_ERROR);
        if (strlen($serializedProperties) > 4096) {
            throw ValidationException::withMessages([
                'properties' => ['Event properties must not exceed 4 KB.'],
            ]);
        }

        $eventId = (string) Str::uuid();
        $timestamp = now('UTC')->format('Y-m-d\TH:i:s.v\Z');

        DB::table('events')->insert([
            'event_id' => $eventId,
            'event_name' => $event['event_name'],
            'visitor_id' => $event['visitor_id'],
            'session_id' => $event['session_id'],
            'source' => 'server',
            'page_path' => $event['page_path'],
            'properties' => $serializedProperties,
            'occurred_at' => $timestamp,
            'received_at' => $timestamp,
        ]);

        return $eventId;
    }
}
