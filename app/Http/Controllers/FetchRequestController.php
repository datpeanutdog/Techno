<?php

namespace App\Http\Controllers;

use App\Models\FetchRequest;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FetchRequestController extends Controller
{
    public function index(): View
    {
        $requests = FetchRequest::query()
            ->with('user:id,name')
            ->live()
            ->latest('id')
            ->limit(30)
            ->get();

        return view('fetch.index', compact('requests'));
    }

    public function create(): View
    {
        return view('fetch.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'item_name' => ['required', 'string', 'max:90'],
            'details' => ['required', 'string', 'max:1000'],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'delivery_location' => ['required', 'string', 'max:100'],
            'duration_value' => [
                'required',
                'integer',
                'min:1',
                'max:1440',
                function (string $attribute, mixed $value, Closure $fail) use ($request): void {
                    $multipliers = [
                        'minutes' => 1,
                        'hours' => 60,
                        'days' => 1440,
                    ];
                    $unit = $request->input('duration_unit');

                    if (! is_string($unit) || ! isset($multipliers[$unit])) {
                        return;
                    }

                    $durationInMinutes = (int) $value * $multipliers[$unit];

                    if ($durationInMinutes < 5 || $durationInMinutes > 1440) {
                        $fail('Choose a duration between 5 minutes and 1 day.');
                    }
                },
            ],
            'duration_unit' => ['required', 'string', 'in:minutes,hours,days'],
        ]);

        $durationInMinutes = (int) $validated['duration_value'] * match ($validated['duration_unit']) {
            'minutes' => 1,
            'hours' => 60,
            'days' => 1440,
        };

        $request->user()->fetchRequests()->create([
            'item_name' => $validated['item_name'],
            'details' => $validated['details'],
            'budget' => $validated['budget'] ?? null,
            'delivery_location' => $validated['delivery_location'],
            'expires_at' => now()->addMinutes($durationInMinutes),
        ]);

        return redirect()
            ->route('fetch.index')
            ->with('status', 'Your fetch request is live.');
    }

    public function updates(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'after' => ['required', 'integer', 'min:0'],
        ]);

        $requests = FetchRequest::query()
            ->with('user:id,name')
            ->live()
            ->where('id', '>', $filters['after'])
            ->oldest('id')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $requests->map(static fn (FetchRequest $fetchRequest): array => [
                'id' => (string) $fetchRequest->getKey(),
                'item_name' => $fetchRequest->item_name,
                'details' => $fetchRequest->details,
                'budget' => $fetchRequest->budget,
                'delivery_location' => $fetchRequest->delivery_location,
                'expires_at' => $fetchRequest->effectiveExpiresAt()->toIso8601String(),
                'expires_at_display' => $fetchRequest->effectiveExpiresAt()->format('M j, g:i A'),
                'requester' => [
                    'name' => $fetchRequest->user->name,
                ],
            ])->values(),
        ]);
    }
}
