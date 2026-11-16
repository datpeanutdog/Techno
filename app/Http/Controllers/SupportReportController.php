<?php

namespace App\Http\Controllers;

use App\Actions\SubmitSupportReport;
use App\Http\Requests\StoreSupportReportRequest;
use App\Models\SupportReport;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SupportReportController extends Controller
{
    public function create(Request $request): View
    {
        return view('support.reports.create', [
            'categories' => SupportReport::CATEGORIES,
            'reportedUser' => User::query()->find($request->integer('reported_user_id')),
        ]);
    }

    public function store(
        StoreSupportReportRequest $request,
        SubmitSupportReport $submitSupportReport,
    ): RedirectResponse {
        $data = $request->validated();
        $reportedUser = isset($data['reported_user_id'])
            ? User::query()->findOrFail($data['reported_user_id'])
            : User::query()->where('email', $data['reported_email'])->firstOrFail();

        $submitSupportReport->handle(
            $request->user(),
            $reportedUser,
            $data['category'],
            $data['details'],
            $data['proofs'] ?? [],
        );

        return redirect()->route('support.reports.mine')
            ->with('status', 'Your report has been sent to our support team.');
    }

    public function mine(Request $request): View
    {
        $reports = $request->user()
            ->supportReportsFiled()
            ->with(['reportedUser:id,name', 'reviewer:id,name'])
            ->latest()
            ->paginate(15);

        return view('support.reports.mine', ['reports' => $reports]);
    }

    public function proof(
        Request $request,
        SupportReport $supportReport,
        int $index,
    ): BinaryFileResponse {
        abort_unless(Gate::allows('view', $supportReport), 404);

        $proofPath = $supportReport->proof_paths[$index] ?? null;
        abort_if(! $proofPath || ! Storage::disk('local')->exists($proofPath), 404);

        return response()->file(Storage::disk('local')->path($proofPath), [
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
