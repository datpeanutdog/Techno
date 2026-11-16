<?php

namespace App\Http\Controllers;

use App\Actions\SubmitSuspensionAppeal;
use App\Models\SuspensionAppeal;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SuspensionAppealController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->isSuspended(), 404);

        return view('account.suspension-support', [
            'user' => $user,
            'appeal' => $user->suspensionAppeals()->with('previousAppeal')->latest('id')->first(),
        ]);
    }

    public function store(Request $request, SubmitSuspensionAppeal $submitSuspensionAppeal): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isSuspended(), 404);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'proof' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $latestAppeal = $user->suspensionAppeals()->latest('id')->first();
        $hasOpenAppeal = $latestAppeal
            && in_array($latestAppeal->status, ['pending', 'under_review'], true);

        if ($hasOpenAppeal) {
            return redirect()->route('account.suspension.show')
                ->with('status', 'Your appeal is already with our support team.');
        }

        $previousAppeal = $latestAppeal?->status === 'denied' ? $latestAppeal : null;
        $submitSuspensionAppeal->handle(
            $user,
            $data['message'],
            $request->file('proof'),
            $previousAppeal,
        );

        return redirect()->route('account.suspension.show')
            ->with('status', 'Your appeal has been sent to our support team.');
    }

    public function proof(Request $request, SuspensionAppeal $suspensionAppeal): BinaryFileResponse
    {
        abort_unless($suspensionAppeal->user_id === $request->user()->id, 404);
        abort_if(! $suspensionAppeal->proof_path
            || ! Storage::disk('local')->exists($suspensionAppeal->proof_path), 404);

        return response()->file(Storage::disk('local')->path($suspensionAppeal->proof_path), [
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
