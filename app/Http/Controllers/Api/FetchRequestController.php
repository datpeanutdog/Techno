<?php

namespace App\Http\Controllers\Api;

use App\Actions\FetchRequestWorkflow;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFetchRequestRequest;
use App\Models\FetchRequest as CampusFetchRequest;
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
            ->map(fn (CampusFetchRequest $fetchRequest): array => $this->payload($fetchRequest, auth()->id()));

        return response()->json(['data' => $requests]);
    }

    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();
        $accepted = $user->acceptedFetchRequests()
            ->with(['user:id,name', 'acceptedBy:id,name'])
            ->latest('accepted_at')
            ->limit(10)
            ->get()
            ->map(fn (CampusFetchRequest $fetchRequest): array => $this->payload($fetchRequest, $user->getKey()));
        $posted = $user->fetchRequests()
            ->with(['user:id,name', 'acceptedBy:id,name'])
            ->whereIn('status', [
                CampusFetchRequest::STATUS_ACCEPTED,
                CampusFetchRequest::STATUS_DELIVERED,
                CampusFetchRequest::STATUS_COMPLETED,
            ])
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (CampusFetchRequest $fetchRequest): array => $this->payload($fetchRequest, $user->getKey()));

        return response()->json([
            'data' => [
                'accepted' => $accepted,
                'posted' => $posted,
            ],
        ]);
    }

    public function store(StoreFetchRequestRequest $request): JsonResponse
    {
        $data = $request->validated();
        $deadlines = $request->deadlineAttributes();
        $fetchRequest = $request->user()->fetchRequests()->create([
            'item_name' => $data['item_name'],
            'details' => $data['details'],
            'budget' => $data['budget'] ?? null,
            'delivery_location' => $data['delivery_location'],
            ...$deadlines,
        ]);
        $fetchRequest->load('user:id,name');

        return response()->json([
            'data' => $this->payload($fetchRequest, $request->user()->getKey()),
            'message' => 'Your fetch request is live.',
        ], 201);
    }

    public function accept(
        Request $request,
        CampusFetchRequest $fetchRequest,
        FetchRequestWorkflow $workflow,
    ): JsonResponse {
        $fetchRequest = $workflow->accept($fetchRequest, $request->user());

        return response()->json([
            'data' => $this->payload($fetchRequest, $request->user()->getKey()),
            'message' => 'You accepted this fetch request.',
        ]);
    }

    public function delivered(
        Request $request,
        CampusFetchRequest $fetchRequest,
        FetchRequestWorkflow $workflow,
    ): JsonResponse {
        $fetchRequest = $workflow->reportDelivery($fetchRequest, $request->user());

        return response()->json([
            'data' => $this->payload($fetchRequest, $request->user()->getKey()),
            'message' => 'Delivery reported. The requester will confirm it.',
        ]);
    }

    public function confirm(
        Request $request,
        CampusFetchRequest $fetchRequest,
        FetchRequestWorkflow $workflow,
    ): JsonResponse {
        $fetchRequest = $workflow->confirmDelivery($fetchRequest, $request->user());

        return response()->json([
            'data' => $this->payload($fetchRequest, $request->user()->getKey()),
            'message' => 'Delivery confirmed.',
        ]);
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
            ->map(fn (CampusFetchRequest $fetchRequest): array => $this->payload($fetchRequest, $request->user()->getKey()));

        return response()->json([
            'data' => $requests,
            'available_ids' => CampusFetchRequest::query()
                ->live()
                ->latest('id')
                ->limit(30)
                ->pluck('id'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(CampusFetchRequest $fetchRequest, ?int $viewerId = null): array
    {
        $expiresAt = $fetchRequest->effectiveExpiresAt();
        $deliveryDeadline = $fetchRequest->effectiveDeliveryDeadline();

        return [
            'id' => $fetchRequest->id,
            'item_name' => $fetchRequest->item_name,
            'details' => $fetchRequest->details,
            'budget' => $fetchRequest->budget,
            'delivery_location' => $fetchRequest->delivery_location,
            'created_at' => $fetchRequest->created_at->toIso8601String(),
            'expires_at' => $expiresAt->toIso8601String(),
            'expires_at_display' => $expiresAt->format('M j, g:i A'),
            'delivery_deadline' => $deliveryDeadline->toIso8601String(),
            'delivery_deadline_display' => $deliveryDeadline->format('M j, g:i A'),
            'status' => $fetchRequest->status,
            'can_accept' => $fetchRequest->status === CampusFetchRequest::STATUS_OPEN
                && (int) $fetchRequest->user_id !== (int) $viewerId
                && $expiresAt->isFuture(),
            'accepted_at' => $fetchRequest->accepted_at?->toIso8601String(),
            'delivered_at' => $fetchRequest->delivered_at?->toIso8601String(),
            'confirmed_at' => $fetchRequest->confirmed_at?->toIso8601String(),
            'requester' => [
                'id' => $fetchRequest->user->getKey(),
                'name' => $fetchRequest->user->name,
            ],
            'accepted_by' => $fetchRequest->acceptedBy
                ? ['id' => $fetchRequest->acceptedBy->getKey(), 'name' => $fetchRequest->acceptedBy->name]
                : null,
        ];
    }
}
