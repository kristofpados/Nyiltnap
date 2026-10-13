<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Forms\RegistrationForm;
use App\Models\Program;
use App\Models\Registration;
use App\Support\SubmissionGuard;
use PDOException;

final class RegistrationController
{
    public function showForm(): void
    {
        // On a shared kiosk tablet the next student must not be able to
        // reach the previous applicant's summary.
        Session::forget('last_registration');

        $this->renderForm();
    }

    public function submit(): void
    {
        $programs = Program::activeById();
        $form = new RegistrationForm($programs);

        if (!Csrf::isValidRequest()) {
            // Keep what the student typed; just ask them to send it again.
            $form->validate($_POST);
            $this->renderForm(
                ['old' => $form->old(), 'formError' => 'A biztonsági ellenőrzés lejárt (például mert sokáig nyitva volt az oldal). Ellenőrizd az adatokat, és küldd el újra a jelentkezést.'],
                403,
            );

            return;
        }

        $token = Request::string($_POST, '_form_token');
        if (SubmissionGuard::wasUsed($token) && is_array(Session::get('last_registration'))) {
            // Same form sent twice (double click / resend): show the first result.
            Response::redirect('/sikeres');
        }

        if (!$form->validate($_POST)) {
            $this->renderForm(['old' => $form->old(), 'errors' => $form->errors()], 422);

            return;
        }

        $data = $form->data();
        try {
            $saved = Registration::create(
                $data['full_name'],
                $data['current_school'],
                $data['residency'],
                $data['is_graduating'],
                $data['program_ids'],
            );
        } catch (PDOException $e) {
            ErrorHandler::report($e);
            $this->renderForm(
                ['old' => $form->old(), 'formError' => 'Most nem sikerült elmenteni a jelentkezést. Az adataidat megtartottuk — kérjük, próbáld újra egy perc múlva.'],
                500,
            );

            return;
        }

        SubmissionGuard::markUsed($token);
        Session::set('last_registration', [
            'reference'      => $saved['reference'],
            'full_name'      => $data['full_name'],
            'current_school' => $data['current_school'],
            'programs'       => array_map(
                static fn (int $id): array => ['name' => $programs[$id]['name'], 'icon' => $programs[$id]['icon']],
                $data['program_ids'],
            ),
            'residency'      => $data['residency'],
            'is_graduating'  => $data['is_graduating'],
            'created_at'     => $saved['created_at'],
        ]);

        // Post/Redirect/Get: refreshing the success page never re-submits.
        Response::redirect('/sikeres');
    }

    public function success(): void
    {
        $registration = Session::get('last_registration');
        if (!is_array($registration)) {
            Response::redirect('/', [], 302);
        }

        $data = ['title' => 'Sikeres regisztráció', 'registration' => $registration];
        Response::html(View::render('public/success', $data + [
            'masthead'      => View::render('public/success-hero', $data),
            'mastheadClass' => 'masthead--success',
        ], 'public'));
    }

    /** @param array{old?: array<string, mixed>, errors?: array<string, string>, formError?: string} $state */
    private function renderForm(array $state = [], int $status = 200): void
    {
        $data = [
            'title'     => $status === 200 ? 'Regisztráció' : 'Javítsd a jelentkezést',
            'programs'  => Program::active(),
            'old'       => $state['old'] ?? ['full_name' => '', 'current_school' => '', 'programs' => [], 'residency' => null, 'graduating' => null],
            'errors'    => $state['errors'] ?? [],
            'formError' => $state['formError'] ?? null,
            'formToken' => SubmissionGuard::issue(),
        ];

        Response::html(View::render('public/form', $data + [
            'masthead'  => View::render('public/hero'),
            'headerCta' => ['href' => '#regisztracio', 'label' => 'Regisztráció'],
        ], 'public'), $status);
    }
}
