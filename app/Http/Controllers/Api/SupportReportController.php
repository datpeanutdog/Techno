<?php

namespace App\Http\Controllers\Api;

use App\Actions\SubmitSupportReport;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupportReportRequest;
use App\Models\SupportReport;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupportReportController extends Controller
{
    public function storeContact(Request $request): JsonResponse
    {
        $data = $request->validate([
            'contact_name' => ['required', 'string', 'max:60'],
            'contact_email' => ['required', 'email', 'max:255'],
            'category' => ['required', Rule::in(array_keys(SupportReport::CONTACT_CATEGORIES))],
            'details' => ['required', 'string', 'max:5000'],
        ]);
        $report = SupportReport::create([
            'contact_name' => $data['contact_name'],
            'contact_email' => $data['contact_email'],
            'category' => $data['category'],
            'details' => $data['details'],
            'proof_paths' => [],
            'status' => 'pending',
        ]);

        return response()->json([
            'data' => ['id' => $report->id, 'category' => $report->category, 'status' => $report->status],
            'message' => 'Your message has been sent to customer support. The team can reach you at the email address you provided.',
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $reports = $request->user()
            ->supportReportsFiled()
            ->with('reportedUser:id,name')
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (SupportReport $report): array => $this->reportPayload($report));

        return response()->json(['data' => $reports]);
    }

    public function store(
        StoreSupportReportRequest $request,
        SubmitSupportReport $submitSupportReport,
    ): JsonResponse {
        $data = $request->validated();
        $reportedUser = isset($data['reported_user_id'])
            ? User::query()->findOrFail($data['reported_user_id'])
            : User::query()->where('email', $data['reported_email'])->firstOrFail();
        $report = $submitSupportReport->handle(
            $request->user(),
            $reportedUser,
            $data['category'],
            $data['details'],
            $data['proofs'] ?? [],
        );
        $report->load('reportedUser:id,name');

        return response()->json([
            'data' => $this->reportPayload($report),
            'message' => 'Your report has been sent to our support team.',
        ], 201);
    }

    /**
     * @return array{id: int, reported_user: ?array{id: int, account_identifier: string, name: string}, category: string, category_label: string, details: string, status: string, proof_count: int, admin_note: ?string, created_at: ?string}
     */
    private function reportPayload(SupportReport $report): array
    {
        return [
            'id' => $report->id,
            'reported_user' => $report->reportedUser ? [
                'id' => $report->reportedUser->id,
                'account_identifier' => $report->reportedUser->account_identifier,
                'name' => $report->reportedUser->name,
            ] : null,
            'category' => $report->category,
            'category_label' => SupportReport::CATEGORIES[$report->category] ?? $report->category,
            'details' => $report->details,
            'status' => $report->status,
            'proof_count' => count($report->proof_paths ?? []),
            'admin_note' => $report->admin_note,
            'created_at' => $report->created_at?->toIso8601String(),
        ];
    }
}
