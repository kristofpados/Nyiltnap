<?php

declare(strict_types=1);

use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\RegistrationsController;
use App\Controllers\Admin\StatisticsController;
use App\Controllers\RegistrationController;
use App\Core\Router;

return static function (Router $router): void {
    // Public registration
    $router->get('/', [RegistrationController::class, 'showForm']);
    $router->post('/', [RegistrationController::class, 'submit']);
    $router->get('/sikeres', [RegistrationController::class, 'success']);

    // Admin authentication
    $router->get('/admin/login', [AuthController::class, 'showLogin']);
    $router->post('/admin/login', [AuthController::class, 'login']);
    $router->post('/admin/logout', [AuthController::class, 'logout']);

    // Admin area (every handler enforces authentication itself)
    $router->get('/admin', [DashboardController::class, 'index']);
    $router->get('/admin/dashboard', [DashboardController::class, 'index']);
    $router->get('/admin/registrations', [RegistrationsController::class, 'index']);
    $router->get('/admin/registrations/export', [RegistrationsController::class, 'export']);
    $router->get('/admin/registrations/{id}/delete', [RegistrationsController::class, 'confirmDelete']);
    $router->post('/admin/registrations/{id}/delete', [RegistrationsController::class, 'delete']);
    $router->get('/admin/statistics', [StatisticsController::class, 'index']);
};
