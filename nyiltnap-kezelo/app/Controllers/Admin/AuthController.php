<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Models\AdminUser;
use App\Models\LoginThrottle;

final class AuthController
{
    /**
     * Hash of a random throwaway string. Verifying against it when the
     * username does not exist keeps response times similar, so timing does
     * not reveal which usernames are real.
     */
    private const DUMMY_HASH = '$2y$10$x1dPyN5HQPfRA6sVIex4gO4D70YgOPQuY7IX0cF1KFraCF0SU5HXu';

    private const GENERIC_FAILURE = 'Hibás felhasználónév vagy jelszó.';

    public function showLogin(): void
    {
        $next = safe_admin_path(Request::query('next'));
        if (Auth::check()) {
            Response::redirect($next, [], 302);
        }

        $this->render(['next' => $next]);
    }

    public function login(): void
    {
        $next = safe_admin_path(Request::string($_POST, 'next'));
        $username = mb_substr(Validator::singleLine($_POST['username'] ?? '') ?? '', 0, 60);
        $form = ['next' => $next, 'username' => $username];

        if (!Csrf::isValidRequest()) {
            $this->render($form + ['error' => 'A biztonsági ellenőrzés lejárt. Kérjük, próbáld újra.'], 403);

            return;
        }

        $ip = Request::ip();
        $wait = LoginThrottle::secondsUntilAllowed($ip);
        if ($wait > 0) {
            header('Retry-After: ' . $wait);
            $minutes = max(1, (int) ceil($wait / 60));
            $this->render($form + ['error' => "Túl sok sikertelen bejelentkezési kísérlet. Biztonsági okból kérjük, várj {$minutes} percet, majd próbáld újra."], 429);

            return;
        }

        $password = Request::string($_POST, 'password');
        $user = ($username !== '' && $password !== '') ? AdminUser::findForLogin($username) : null;

        $passwordOk = password_verify($password, $user['password_hash'] ?? self::DUMMY_HASH);
        if ($user === null || !$passwordOk) {
            LoginThrottle::recordFailure($ip);
            $this->render($form + ['error' => self::GENERIC_FAILURE], 422);

            return;
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT) && AdminUser::passwordProblem($password) === null) {
            AdminUser::updatePassword($user['id'], $password);
        }

        LoginThrottle::clear($ip);
        AdminUser::recordLogin($user['id']);
        Auth::login($user);

        Response::redirect($next);
    }

    public function logout(): void
    {
        if (!Auth::check()) {
            Response::redirect('/admin/login');
        }
        Csrf::verify();

        Auth::logout();
        Session::flash('success', 'Sikeresen kijelentkeztél.');
        Response::redirect('/admin/login');
    }

    /** @param array<string, mixed> $data */
    private function render(array $data, int $status = 200): void
    {
        header('X-Robots-Tag: noindex, nofollow');
        Response::html(View::render('admin/login', $data + [
            'title'    => 'Bejelentkezés',
            'username' => '',
            'error'    => null,
            'flashes'  => Session::takeFlashes(),
        ], 'auth'), $status);
    }
}
