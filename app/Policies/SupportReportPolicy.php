<?php

namespace App\Policies;

use App\Models\SupportReport;
use App\Models\User;

class SupportReportPolicy
{
    public function view(User $user, SupportReport $supportReport): bool
    {
        return $user->isAdmin() || $supportReport->reporter_id === $user->id;
    }
}
