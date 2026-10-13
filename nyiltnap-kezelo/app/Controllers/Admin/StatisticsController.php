<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Models\Program;
use App\Models\Statistics;

final class StatisticsController extends AdminController
{
    public function index(): void
    {
        $admin = Auth::requireAdmin();

        $this->render('admin/statistics', [
            'title'          => 'Statisztikák',
            'active'         => 'statistics',
            'admin'          => $admin,
            'overview'       => Statistics::overview(),
            'byProgram'      => Statistics::byProgram(),
            'daily'          => Statistics::daily(30),
            'selections'     => Statistics::programSelections(),
            'represented'    => Statistics::programsRepresented(),
            'activePrograms' => count(Program::active()),
        ]);
    }
}
