<?php

namespace App\Actions;

use App\Models\FetchRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FetchRequestWorkflow
{
    public function accept(FetchRequest $fetchRequest, User $runner): FetchRequest
    {
        return DB::transaction(function () use ($fetchRequest, $runner): FetchRequest {
            $lockedRequest = FetchRequest::query()
                ->lockForUpdate()
                ->findOrFail($fetchRequest->getKey());

            if ((int) $lockedRequest->user_id === (int) $runner->getKey()) {
                throw ValidationException::withMessages([
                    'fetch_request' => 'You cannot accept your own fetch request.',
                ]);
            }

            if (
                $lockedRequest->status !== FetchRequest::STATUS_OPEN
                || $lockedRequest->effectiveExpiresAt()->lessThanOrEqualTo(now())
                || $lockedRequest->effectiveDeliveryDeadline()->lessThanOrEqualTo(now())
            ) {
                throw ValidationException::withMessages([
                    'fetch_request' => 'This fetch request is no longer available.',
                ]);
            }

            $lockedRequest->forceFill([
                'status' => FetchRequest::STATUS_ACCEPTED,
                'accepted_by_id' => $runner->getKey(),
                'accepted_at' => now(),
            ])->save();

            return $lockedRequest->load(['user:id,name', 'acceptedBy:id,name']);
        }, attempts: 3);
    }

    public function reportDelivery(FetchRequest $fetchRequest, User $runner): FetchRequest
    {
        return DB::transaction(function () use ($fetchRequest, $runner): FetchRequest {
            $lockedRequest = FetchRequest::query()
                ->lockForUpdate()
                ->findOrFail($fetchRequest->getKey());

            if ((int) $lockedRequest->accepted_by_id !== (int) $runner->getKey()) {
                throw new AuthorizationException('You are not assigned to this fetch request.');
            }

            if ($lockedRequest->status !== FetchRequest::STATUS_ACCEPTED) {
                throw ValidationException::withMessages([
                    'fetch_request' => 'This fetch request is not awaiting delivery.',
                ]);
            }

            $deliveredAt = now();

            if ($deliveredAt->greaterThan($lockedRequest->effectiveDeliveryDeadline())) {
                throw ValidationException::withMessages([
                    'fetch_request' => 'The delivery deadline has passed. This request cannot be marked delivered.',
                ]);
            }

            $lockedRequest->forceFill([
                'status' => FetchRequest::STATUS_DELIVERED,
                'delivered_at' => $deliveredAt,
            ])->save();

            return $lockedRequest->load(['user:id,name', 'acceptedBy:id,name']);
        }, attempts: 3);
    }

    public function confirmDelivery(FetchRequest $fetchRequest, User $requester): FetchRequest
    {
        return DB::transaction(function () use ($fetchRequest, $requester): FetchRequest {
            $lockedRequest = FetchRequest::query()
                ->lockForUpdate()
                ->findOrFail($fetchRequest->getKey());

            if ((int) $lockedRequest->user_id !== (int) $requester->getKey()) {
                throw new AuthorizationException('Only the requester can confirm this delivery.');
            }

            if (
                $lockedRequest->status !== FetchRequest::STATUS_DELIVERED
                || ! $lockedRequest->delivered_at
                || $lockedRequest->delivered_at->greaterThan($lockedRequest->effectiveDeliveryDeadline())
            ) {
                throw ValidationException::withMessages([
                    'fetch_request' => 'There is no on-time delivery waiting for confirmation.',
                ]);
            }

            $lockedRequest->forceFill([
                'status' => FetchRequest::STATUS_COMPLETED,
                'confirmed_at' => now(),
            ])->save();

            return $lockedRequest->load(['user:id,name', 'acceptedBy:id,name']);
        }, attempts: 3);
    }
}
