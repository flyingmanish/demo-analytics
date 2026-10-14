<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnalyticsSummaryController
{
    public function show(Request $request): JsonResponse
    {
        $dates = $request->validate([
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d'],
        ]);
        $from = $dates['from'] ?? now('UTC')->toDateString();
        $to = $dates['to'] ?? now('UTC')->toDateString();

        if ($from > $to) {
            throw ValidationException::withMessages([
                'from' => ['The start date must be no later than the end date.'],
            ]);
        }

        $startDate = Carbon::parse($from, 'UTC');
        $endDate = Carbon::parse($to, 'UTC');
        if ($startDate->diffInDays($endDate) + 1 > 90) {
            throw ValidationException::withMessages([
                'to' => ['The date range must not exceed 90 days.'],
            ]);
        }

        $start = $startDate->copy()->startOfDay()->format('Y-m-d\TH:i:s.v\Z');
        $end = $endDate->copy()->addDay()->startOfDay()->format('Y-m-d\TH:i:s.v\Z');
        $range = fn ($query) => $query->where('occurred_at', '>=', $start)->where('occurred_at', '<', $end);

        $totals = $range(DB::table('events'))
            ->selectRaw("COUNT(*) as total_events, SUM(CASE WHEN event_name = 'page_view' THEN 1 ELSE 0 END) as page_views, COUNT(DISTINCT CASE WHEN event_name = 'page_view' THEN visitor_id END) as unique_visitors")
            ->first();

        $dailyRows = $range(DB::table('events'))
            ->selectRaw("substr(occurred_at, 1, 10) as day, COUNT(*) as events, SUM(CASE WHEN event_name = 'page_view' THEN 1 ELSE 0 END) as page_views")
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $daily = [];
        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $day = $date->toDateString();
            $row = $dailyRows->get($day);
            $daily[] = [
                'day' => $day,
                'events' => (int) ($row->events ?? 0),
                'page_views' => (int) ($row->page_views ?? 0),
            ];
        }

        $popularPages = $range(DB::table('events'))
            ->where('event_name', 'page_view')
            ->whereNotNull('page_path')
            ->selectRaw('page_path, COUNT(*) as views, COUNT(DISTINCT visitor_id) as visitors')
            ->groupBy('page_path')
            ->orderByDesc('views')
            ->orderBy('page_path')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'page_path' => $row->page_path,
                'views' => (int) $row->views,
                'visitors' => (int) $row->visitors,
            ]);

        $eventCounts = $range(DB::table('events'))
            ->selectRaw('event_name, COUNT(*) as count')
            ->groupBy('event_name')
            ->orderByDesc('count')
            ->orderBy('event_name')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'event_name' => $row->event_name,
                'count' => (int) $row->count,
            ]);

        return response()->json([
            'from' => $from,
            'to' => $to,
            'totals' => [
                'total_events' => (int) ($totals->total_events ?? 0),
                'page_views' => (int) ($totals->page_views ?? 0),
                'unique_visitors' => (int) ($totals->unique_visitors ?? 0),
            ],
            'daily' => $daily,
            'popular_pages' => $popularPages,
            'event_counts' => $eventCounts,
        ]);
    }
}
