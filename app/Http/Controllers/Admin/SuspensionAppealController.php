<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SuspensionAppeal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SuspensionAppealController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'active');
        abort_unless(in_array($tab, ['active', 'resolved'], true), 404);

        $appeals = SuspensionAppeal::query()
            ->with(['user:id,name,email,suspended_at', 'reviewer:id,name'])
            ->whereIn('status', $tab === 'active' ? ['pending', 'under_review'] : ['approved', 'denied'])
            ->latest()
            ->paginate(20);

        return view('admin.suspension-appeals.index', [
            'appeals' => $appeals->withQueryString(),
            'tab' => $tab,
        ]);
    }

    public function show(SuspensionAppeal $suspensionAppeal): View
    {
        $suspensionAppeal->load([
            'user:id,name,email,suspended_at,suspension_reason',
            'previousAppeal',
            'reviewer:id,name',
        ]);

        return view('admin.suspension-appeals.show', ['appeal' => $suspensionAppeal]);
    }

    public function proof(SuspensionAppeal $suspensionAppeal): BinaryFileResponse
    {
        abort_if(! $suspensionAppeal->proof_path
            || ! Storage::disk('local')->exists($suspensionAppeal->proof_path), 404);

        return response()->file(Storage::disk('local')->path($suspensionAppeal->proof_path), [
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    public function update(Request $request, SuspensionAppeal $suspensionAppeal): RedirectResponse
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

        return redirect()->route('admin.suspension-appeals.show', $suspensionAppeal)
            ->with('status', 'The suspension appeal has been updated.');
    }
}
