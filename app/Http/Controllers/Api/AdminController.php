<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportReport;
use App\Models\SuspensionAppeal;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function users(Request $request): JsonResponse
    {
        $users = User::query()
            ->select(['id', 'name', 'email', 'role'])
            ->orderBy('name')
            ->paginate(25);

        return response()->json([
            'data' => collect($users->items())->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'account_identifier' => $user->account_identifier,
                'can_change_role' => ! $request->user()->is($user),
            ]),
            'meta' => $this->pagination($users),
        ]);
    }

    public function updateRole(Request $request, User $user): JsonResponse
    {
        abort_if($request->user()->is($user), 403);
        $data = $request->validate([
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_USER])],
        ]);
        $user->forceFill(['role' => $data['role']])->save();

        return response()->json([
            'data' => ['id' => $user->id, 'role' => $user->role],
            'message' => 'The account role has been updated.',
        ]);
    }

    public function supportReports(): JsonResponse
    {
        $reports = SupportReport::query()
            ->with(['reporter:id,name,email', 'reportedUser:id,name,email'])
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => collect($reports->items())->map(fn (SupportReport $report): array => $this->supportReportPayload($report)),
            'meta' => $this->pagination($reports),
        ]);
    }

    public function supportReport(SupportReport $supportReport): JsonResponse
    {
        $supportReport->load([
            'reporter:id,name,email',
            'reportedUser:id,name,email,suspended_at,suspension_reason',
            'reviewer:id,name',
        ]);
        $payload = $this->supportReportPayload($supportReport);
        $payload['proof_images'] = collect($supportReport->proof_paths ?? [])
            ->map(fn (string $path): ?string => $this->imageDataUri($path))
            ->filter()
            ->values();

        return response()->json(['data' => $payload]);
    }

    public function updateSupportReport(Request $request, SupportReport $supportReport): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['reviewed', 'resolved', 'dismissed'])],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $supportReport->forceFill([
            'status' => $data['status'],
            'admin_note' => $data['admin_note'] ?? null,
            'reviewed_by' => $request->user()->id,
            'resolved_at' => in_array($data['status'], ['resolved', 'dismissed'], true) ? now() : null,
        ])->save();

        return response()->json([
            'data' => $this->supportReportPayload($supportReport->fresh(['reporter:id,name,email', 'reportedUser:id,name,email'])),
            'message' => 'The report has been updated.',
        ]);
    }

    public function suspendReportedUser(Request $request, SupportReport $supportReport): JsonResponse
    {
        $reportedUser = $supportReport->reportedUser;
        abort_if($reportedUser === null, 404);

        DB::transaction(function () use ($request, $reportedUser, $supportReport): void {
            $reportedUser->forceFill([
                'suspended_at' => now(),
                'suspension_reason' => $supportReport->details,
                'suspended_by' => $request->user()->id,
            ])->save();
            $reportedUser->tokens()->delete();

            $supportReport->forceFill([
                'status' => 'resolved',
                'admin_note' => 'The reported account was suspended.',
                'reviewed_by' => $request->user()->id,
                'resolved_at' => now(),
            ])->save();
        });

        return response()->json(['message' => 'The account has been suspended and its API sessions revoked.']);
    }

    public function restoreReportedUser(SupportReport $supportReport): JsonResponse
    {
        $reportedUser = $supportReport->reportedUser;
        abort_if($reportedUser === null, 404);
        $reportedUser->forceFill([
            'suspended_at' => null,
            'suspension_reason' => null,
            'suspended_by' => null,
        ])->save();

        return response()->json(['message' => 'The reported account has been restored.']);
    }

    public function suspensionAppeals(Request $request): JsonResponse
    {
        $tab = $request->query('tab', 'active');
        abort_unless(in_array($tab, ['active', 'resolved'], true), 404);
        $appeals = SuspensionAppeal::query()
            ->with(['user:id,name,email,suspended_at', 'reviewer:id,name'])
            ->whereIn('status', $tab === 'active' ? ['pending', 'under_review'] : ['approved', 'denied'])
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => collect($appeals->items())->map(fn (SuspensionAppeal $appeal): array => $this->appealPayload($appeal)),
            'meta' => $this->pagination($appeals),
        ]);
    }

    public function suspensionAppeal(SuspensionAppeal $suspensionAppeal): JsonResponse
    {
        $suspensionAppeal->load([
            'user:id,name,email,suspended_at,suspension_reason',
            'previousAppeal',
            'reviewer:id,name',
        ]);

        return response()->json(['data' => $this->appealPayload($suspensionAppeal, true)]);
    }

    public function updateSuspensionAppeal(Request $request, SuspensionAppeal $suspensionAppeal): JsonResponse
    {
        abort_unless(in_array($suspensionAppeal->status, ['pending', 'under_review'], true), 404);
        $data = $request->validate([
            'status' => ['required', Rule::in(['under_review', 'approved', 'denied'])],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $suspensionAppeal, $data): void {
            if ($data['status'] === 'approved' && $suspensionAppeal->user) {
                $suspensionAppeal->user->forceFill([
                    'suspended_at' => null,
                    'suspension_reason' => null,
                    'suspended_by' => null,
                ])->save();
            }

            $suspensionAppeal->forceFill([
                'status' => $data['status'],
                'admin_note' => $data['admin_note'] ?? null,
                'reviewed_by' => $request->user()->id,
                'resolved_at' => $data['status'] === 'under_review' ? null : now(),
            ])->save();
        });

        $suspensionAppeal->refresh()->load(['user:id,name,email,suspended_at,suspension_reason', 'previousAppeal']);

        return response()->json([
            'data' => $this->appealPayload($suspensionAppeal, true),
            'message' => 'The suspension appeal has been updated.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function supportReportPayload(SupportReport $report): array
    {
        return [
            'id' => $report->id,
            'category' => $report->category,
            'category_label' => SupportReport::CATEGORIES[$report->category] ?? $report->category,
            'details' => $report->details,
            'status' => $report->status,
            'admin_note' => $report->admin_note,
            'proof_count' => count($report->proof_paths ?? []),
            'created_at' => $report->created_at?->toIso8601String(),
            'contact_name' => $report->contact_name,
            'contact_email' => $report->contact_email,
            'reporter' => $report->reporter ? [
                'id' => $report->reporter->id,
                'name' => $report->reporter->name,
                'email' => $report->reporter->email,
                'account_identifier' => $report->reporter->account_identifier,
            ] : null,
            'reported_user' => $report->reportedUser ? [
                'id' => $report->reportedUser->id,
                'name' => $report->reportedUser->name,
                'email' => $report->reportedUser->email,
                'account_identifier' => $report->reportedUser->account_identifier,
                'is_suspended' => $report->reportedUser->isSuspended(),
                'suspension_reason' => $report->reportedUser->suspension_reason,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function appealPayload(SuspensionAppeal $appeal, bool $includeDetails = false): array
    {
        $payload = [
            'id' => $appeal->id,
            'message' => $appeal->message,
            'status' => $appeal->status,
            'admin_note' => $appeal->admin_note,
            'created_at' => $appeal->created_at?->toIso8601String(),
            'user' => $appeal->user ? [
                'id' => $appeal->user->id,
                'name' => $appeal->user->name,
                'email' => $appeal->user->email,
                'account_identifier' => $appeal->user->account_identifier,
                'is_suspended' => $appeal->user->isSuspended(),
                'suspension_reason' => $appeal->user->suspension_reason,
            ] : null,
        ];

        if ($includeDetails) {
            $payload['proof_image'] = $this->imageDataUri($appeal->proof_path);
            $payload['previous_appeal'] = $appeal->previousAppeal ? [
                'id' => $appeal->previousAppeal->id,
                'message' => $appeal->previousAppeal->message,
                'status' => $appeal->previousAppeal->status,
                'admin_note' => $appeal->previousAppeal->admin_note,
            ] : null;
        }

        return $payload;
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

    /**
     * @return array{current_page: int, last_page: int, total: int}
     */
    private function pagination($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ];
    }
}
