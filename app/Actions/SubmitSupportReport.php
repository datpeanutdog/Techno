<?php

namespace App\Actions;

use App\Models\SupportReport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class SubmitSupportReport
{
    /**
     * @param  array<int, UploadedFile>  $proofs
     */
    public function handle(
        User $reporter,
        User $reportedUser,
        string $category,
        string $details,
        array $proofs,
    ): SupportReport {
        $proofPaths = [];

        try {
            foreach ($proofs as $proof) {
                $path = $proof->store('support-reports', 'local');

                if ($path === false) {
                    throw new RuntimeException('A proof image could not be saved.');
                }

                $proofPaths[] = $path;
            }

            return SupportReport::create([
                'reporter_id' => $reporter->id,
                'reported_user_id' => $reportedUser->id,
                'category' => $category,
                'details' => $details,
                'proof_paths' => $proofPaths,
                'status' => 'pending',
            ]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($proofPaths);

            throw $exception;
        }
    }
}
