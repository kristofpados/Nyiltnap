<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Models\Program;
use App\Models\Registration;
use App\Models\Statistics;

final class DashboardController extends AdminController
{
    public function index(): void
    {
        $admin = Auth::requireAdmin();

        $this->render('admin/dashboard', [
            'title'          => 'Áttekintés',
            'active'         => 'dashboard',
            'admin'          => $admin,
            'overview'       => Statistics::overview(),
            'byProgram'      => Statistics::byProgram(),
            'represented'    => Statistics::programsRepresented(),
            'activePrograms' => count(Program::active()),
            'recent'         => Registration::recent((int) config('admin.recent_count', 8)),
        ]);
    }
}
