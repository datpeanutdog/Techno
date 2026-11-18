<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupportReportController extends Controller
{
    public function index(): View
    {
        $reports = SupportReport::query()
            ->with(['reporter:id,name,email', 'reportedUser:id,name,email'])
            ->latest()
            ->paginate(20);

        return view('admin.support-reports.index', ['reports' => $reports]);
    }

    public function show(SupportReport $supportReport): View
    {
        $supportReport->load([
            'reporter:id,name,email',
            'reportedUser:id,name,email,suspended_at,suspension_reason',
            'reviewer:id,name',
        ]);

        return view('admin.support-reports.show', ['report' => $supportReport]);
    }

    public function update(Request $request, SupportReport $supportReport): RedirectResponse
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

        return back()->with('status', 'The report has been updated.');
    }

    public function suspend(Request $request, SupportReport $supportReport): RedirectResponse
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

        return back()->with('status', 'The account has been suspended and its API sessions revoked.');
    }

    public function restore(Request $request, SupportReport $supportReport): RedirectResponse
    {
        $reportedUser = $supportReport->reportedUser;
        abort_if($reportedUser === null, 404);

        $reportedUser->forceFill([
            'suspended_at' => null,
            'suspension_reason' => null,
            'suspended_by' => null,
        ])->save();

        return back()->with('status', 'The reported account has been restored.');
    }
}
