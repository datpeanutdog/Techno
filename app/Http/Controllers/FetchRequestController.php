<?php

namespace App\Http\Controllers;

use App\Actions\FetchRequestWorkflow;
use App\Http\Requests\StoreFetchRequestRequest as StoreFetchRequestHttpRequest;
use App\Models\FetchRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FetchRequestController extends Controller
{
    public function index(Request $request): View
    {
        $requests = FetchRequest::query()
            ->with('user:id,name')
            ->live()
            ->latest('id')
            ->limit(30)
            ->get();
        $acceptedJobs = $request->user()->acceptedFetchRequests()
            ->with('user:id,name')
            ->latest('accepted_at')
            ->limit(10)
            ->get();
        $postedRequests = $request->user()->fetchRequests()
            ->with('acceptedBy:id,name')
            ->whereIn('status', [
                FetchRequest::STATUS_ACCEPTED,
                FetchRequest::STATUS_DELIVERED,
                FetchRequest::STATUS_COMPLETED,
            ])
            ->latest('id')
            ->limit(10)
            ->get();

        return view('fetch.index', compact('requests', 'acceptedJobs', 'postedRequests'));
    }

    public function create(): View
    {
        return view('fetch.create');
    }

    public function store(StoreFetchRequestHttpRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $deadlines = $request->deadlineAttributes();

        $request->user()->fetchRequests()->create([
            'item_name' => $validated['item_name'],
            'details' => $validated['details'],
            'budget' => $validated['budget'] ?? null,
            'delivery_location' => $validated['delivery_location'],
            ...$deadlines,
        ]);

        return redirect()
            ->route('fetch.index')
            ->with('status', 'Your fetch request is live.');
    }

    public function accept(
        Request $request,
        FetchRequest $fetchRequest,
        FetchRequestWorkflow $workflow,
    ): RedirectResponse {
        $workflow->accept($fetchRequest, $request->user());

        return redirect()
            ->route('fetch.index')
            ->with('status', 'You accepted this fetch request.');
    }

    public function delivered(
        Request $request,
        FetchRequest $fetchRequest,
        FetchRequestWorkflow $workflow,
    ): RedirectResponse {
        $workflow->reportDelivery($fetchRequest, $request->user());

        return redirect()
            ->route('fetch.index')
            ->with('status', 'Delivery reported. The requester will confirm it.');
    }

    public function confirm(
        Request $request,
        FetchRequest $fetchRequest,
        FetchRequestWorkflow $workflow,
    ): RedirectResponse {
        $workflow->confirmDelivery($fetchRequest, $request->user());

        return redirect()
            ->route('fetch.index')
            ->with('status', 'Delivery confirmed.');
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
                'created_at' => $fetchRequest->created_at->toIso8601String(),
                'expires_at' => $fetchRequest->effectiveExpiresAt()->toIso8601String(),
                'expires_at_display' => $fetchRequest->effectiveExpiresAt()->format('M j, g:i A'),
                'delivery_deadline' => $fetchRequest->effectiveDeliveryDeadline()->toIso8601String(),
                'delivery_deadline_display' => $fetchRequest->effectiveDeliveryDeadline()->format('M j, g:i A'),
                'status' => $fetchRequest->status,
                'can_accept' => (int) $fetchRequest->user_id !== (int) $request->user()->getKey(),
                'requester' => [
                    'id' => $fetchRequest->user->getKey(),
                    'name' => $fetchRequest->user->name,
                ],
            ])->values(),
            'available_ids' => FetchRequest::query()
                ->live()
                ->latest('id')
                ->limit(30)
                ->pluck('id'),
        ]);
    }
}
