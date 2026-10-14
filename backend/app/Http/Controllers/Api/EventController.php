<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EventController
{
    public function store(Request $request): JsonResponse
    {
        $hasServerKey = $request->headers->has('x-analytics-key');
        $serverKey = (string) config('analytics.server_api_key');

        if ($hasServerKey && ($serverKey === '' || ! hash_equals($serverKey, (string) $request->header('x-analytics-key')))) {
            return response()->json(['error' => 'Invalid analytics API key.'], 401);
        }

        $source = $hasServerKey ? 'server' : 'browser';
        $validated = Validator::make($request->all(), [
            'events' => ['required', 'array', 'min:1', 'max:50'],
        ])->validate();

        if (! array_is_list($validated['events'])) {
            throw ValidationException::withMessages([
                'events' => ['The events field must be a list of event objects.'],
            ]);
        }

        $receivedAt = now('UTC')->format('Y-m-d\TH:i:s.v\Z');
        $events = [];

        foreach ($validated['events'] as $index => $input) {
            if (! is_array($input) || array_is_list($input)) {
                throw ValidationException::withMessages([
                    "events.$index" => ['Each event must be an object.'],
                ]);
            }

            $event = Validator::make($input, [
                'event_id' => ['nullable', 'string', 'regex:/^[a-zA-Z0-9_-]{1,128}$/'],
                'event_name' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{0,63}$/'],
                'visitor_id' => ['required', 'string', 'regex:/^[a-zA-Z0-9_-]{1,128}$/'],
                'session_id' => ['required', 'string', 'regex:/^[a-zA-Z0-9_-]{1,128}$/'],
                'page_path' => ['nullable', 'string', 'max:512'],
                'properties' => ['nullable', 'array'],
                'occurred_at' => ['nullable', 'string', 'date'],
            ])->validate();

            if ($source === 'browser' && $event['event_name'] === 'order_created') {
                throw ValidationException::withMessages([
                    "events.$index.event_name" => ['order_created events must be submitted by a trusted server.'],
                ]);
            }

            $pagePath = $event['page_path'] ?? null;
            if ($pagePath !== null && ! str_starts_with($pagePath, '/')) {
                throw ValidationException::withMessages([
                    "events.$index.page_path" => ["The page_path field must begin with '/'."],
                ]);
            }

            $properties = $event['properties'] ?? [];
            if ($properties !== [] && array_is_list($properties)) {
                throw ValidationException::withMessages([
                    "events.$index.properties" => ['Event properties must be a JSON object.'],
                ]);
            }
            $serializedProperties = json_encode((object) $properties, JSON_THROW_ON_ERROR);
            if (strlen($serializedProperties) > 4096) {
                throw ValidationException::withMessages([
                    "events.$index.properties" => ['Event properties must not exceed 4 KB.'],
                ]);
            }

            $occurredAt = isset($event['occurred_at'])
                ? Carbon::parse($event['occurred_at'], 'UTC')
                : now('UTC');
            $now = now('UTC');
            if ($occurredAt->lt($now->copy()->subDays(30)) || $occurredAt->gt($now->copy()->addMinutes(5))) {
                throw ValidationException::withMessages([
                    "events.$index.occurred_at" => ['The event time must be within the last 30 days and no more than 5 minutes in the future.'],
                ]);
            }

            $events[] = [
                'event_id' => ($event['event_id'] ?? null) ?: (string) Str::uuid(),
                'event_name' => $event['event_name'],
                'visitor_id' => $event['visitor_id'],
                'session_id' => $event['session_id'],
                'source' => $source,
                'page_path' => $pagePath,
                'properties' => $serializedProperties,
                'occurred_at' => $occurredAt->utc()->format('Y-m-d\TH:i:s.v\Z'),
                'received_at' => $receivedAt,
            ];
        }

        $accepted = DB::transaction(fn () => DB::table('events')->insertOrIgnore($events));

        return response()->json([
            'accepted' => $accepted,
            'duplicates' => count($events) - $accepted,
        ], 202);
    }
}
