<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Response;
use App\Core\Session;
use App\Core\View;

abstract class AdminController
{
    /** @param array<string, mixed> $data */
    protected function render(string $template, array $data, int $status = 200): void
    {
        header('X-Robots-Tag: noindex, nofollow');
        $data['flashes'] = Session::takeFlashes();

        Response::html(View::render($template, $data, 'admin'), $status);
    }
}
