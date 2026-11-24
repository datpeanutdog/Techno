<?php

namespace App\Http\Controllers\Api;

use App\Actions\SubmitSuspensionAppeal;
use App\Http\Controllers\Controller;
use App\Models\SuspensionAppeal;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SuspensionAppealController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isSuspended(), 403);

        $appeal = $user->suspensionAppeals()
            ->with('previousAppeal')
            ->latest('id')
            ->first();

        return response()->json([
            'data' => [
                'user' => [
                    'name' => $user->name,
                    'account_identifier' => $user->account_identifier,
                    'email' => $user->email,
                    'suspended_at' => $user->suspended_at?->toIso8601String(),
                    'suspension_reason' => $user->suspension_reason,
                ],
                'appeal' => $appeal ? $this->appealPayload($appeal) : null,
            ],
        ]);
    }

    public function store(Request $request, SubmitSuspensionAppeal $submitSuspensionAppeal): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isSuspended(), 403);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'proof' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $latestAppeal = $user->suspensionAppeals()->latest('id')->first();

        if ($latestAppeal && in_array($latestAppeal->status, ['pending', 'under_review'], true)) {
            return response()->json(['message' => 'Your appeal is already with our support team.'], 409);
        }

        $previousAppeal = $latestAppeal?->status === 'denied' ? $latestAppeal : null;
        $appeal = $submitSuspensionAppeal->handle(
            $user,
            $data['message'],
            $request->file('proof'),
            $previousAppeal,
        );
        $appeal->refresh()->load('previousAppeal');

        return response()->json([
            'data' => $this->appealPayload($appeal),
            'message' => 'Your appeal has been sent to our support team.',
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function appealPayload(SuspensionAppeal $appeal): array
    {
        return [
            'id' => $appeal->id,
            'message' => $appeal->message,
            'status' => $appeal->status,
            'admin_note' => $appeal->admin_note,
            'created_at' => $appeal->created_at?->toIso8601String(),
            'proof_image' => $this->imageDataUri($appeal->proof_path),
            'previous_appeal' => $appeal->previousAppeal ? [
                'id' => $appeal->previousAppeal->id,
                'message' => $appeal->previousAppeal->message,
                'status' => $appeal->previousAppeal->status,
                'admin_note' => $appeal->previousAppeal->admin_note,
            ] : null,
        ];
    }

    private function imageDataUri(?string $path): ?string
    {
        if (! $path || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        $contents = Storage::disk('local')->get($path);
        $mimeType = Storage::disk('local')->mimeType($path) ?: 'application/octet-stream';

        return 'data:'.$mimeType.';base64,'.base64_encode($contents);
    }
}
