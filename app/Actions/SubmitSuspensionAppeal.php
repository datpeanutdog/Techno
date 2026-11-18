<?php

namespace App\Actions;

use App\Models\SuspensionAppeal;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class SubmitSuspensionAppeal
{
    public function handle(
        User $user,
        string $message,
        ?UploadedFile $proof,
        ?SuspensionAppeal $previousAppeal,
    ): SuspensionAppeal {
        $proofPath = null;

        try {
            if ($proof) {
                $proofPath = $proof->store('suspension-appeals', 'local');

                if ($proofPath === false) {
                    throw new RuntimeException('The appeal image could not be saved.');
                }
            }

            return DB::transaction(fn (): SuspensionAppeal => $user->suspensionAppeals()->create([
                'message' => $message,
                'previous_appeal_id' => $previousAppeal?->id,
                'proof_path' => $proofPath,
            ]));
        } catch (Throwable $exception) {
            if ($proofPath) {
                Storage::disk('local')->delete($proofPath);
            }

            throw $exception;
        }
    }
}
