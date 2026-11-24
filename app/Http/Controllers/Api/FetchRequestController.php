<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FetchRequest as CampusFetchRequest;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FetchRequestController extends Controller
{
    public function index(): JsonResponse
    {
        $requests = CampusFetchRequest::query()
            ->with('user:id,name')
            ->live()
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(fn (CampusFetchRequest $fetchRequest): array => $this->payload($fetchRequest));

        return response()->json(['data' => $requests]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
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
                    $multipliers = ['minutes' => 1, 'hours' => 60, 'days' => 1440];
                    $unit = $request->input('duration_unit');

                    if (is_string($unit) && isset($multipliers[$unit])) {
                        $minutes = (int) $value * $multipliers[$unit];
                        if ($minutes < 5 || $minutes > 1440) {
                            $fail('Choose a duration between 5 minutes and 1 day.');
                        }
                    }
                },
            ],
            'duration_unit' => ['required', 'string', 'in:minutes,hours,days'],
        ]);
        $durationInMinutes = (int) $data['duration_value'] * match ($data['duration_unit']) {
            'minutes' => 1,
            'hours' => 60,
            'days' => 1440,
        };

        $fetchRequest = $request->user()->fetchRequests()->create([
            'item_name' => $data['item_name'],
            'details' => $data['details'],
            'budget' => $data['budget'] ?? null,
            'delivery_location' => $data['delivery_location'],
            'expires_at' => now()->addMinutes($durationInMinutes),
        ]);
        $fetchRequest->load('user:id,name');

        return response()->json([
            'data' => $this->payload($fetchRequest),
            'message' => 'Your fetch request is live.',
        ], 201);
    }

    public function updates(Request $request): JsonResponse
    {
        $filters = $request->validate(['after' => ['required', 'integer', 'min:0']]);
        $requests = CampusFetchRequest::query()
            ->with('user:id,name')
            ->live()
            ->where('id', '>', $filters['after'])
            ->oldest('id')
            ->limit(50)
            ->get()
            ->map(fn (CampusFetchRequest $fetchRequest): array => $this->payload($fetchRequest));

        return response()->json(['data' => $requests]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(CampusFetchRequest $fetchRequest): array
    {
        $expiresAt = $fetchRequest->effectiveExpiresAt();

        return [
            'id' => $fetchRequest->id,
            'item_name' => $fetchRequest->item_name,
            'details' => $fetchRequest->details,
            'budget' => $fetchRequest->budget,
            'delivery_location' => $fetchRequest->delivery_location,
            'expires_at' => $expiresAt->toIso8601String(),
            'expires_at_display' => $expiresAt->format('M j, g:i A'),
            'requester' => ['name' => $fetchRequest->user->name],
        ];
    }
}
