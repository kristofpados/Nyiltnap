<?php

declare(strict_types=1);

/*
 * Admin fiók létrehozása vagy jelszócsere.
 *
 * Interaktív (ajánlott):
 *   php scripts/create_admin.php
 *   php scripts/create_admin.php felhasznalonev
 *
 * Automatizáláshoz (a jelszó a standard bemenet első sora):
 *   php scripts/create_admin.php felhasznalonev --password-stdin < jelszo.txt
 *
 * Meglévő fiók jelszavának cseréje: --update
 *
 * A jelszó csak password_hash() kimenetként kerül az adatbázisba; a script
 * soha nem írja ki és nem menti fájlba.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Models\AdminUser;

$args = array_slice($argv, 1);
$fromStdin = in_array('--password-stdin', $args, true);
$update = in_array('--update', $args, true);
$positional = array_values(array_filter($args, static fn (string $a): bool => !str_starts_with($a, '--')));
$interactive = function_exists('stream_isatty') ? stream_isatty(STDIN) : true;

/** Reads one line from the terminal with echo disabled where the platform allows it. */
function prompt_hidden(string $label): string
{
    fwrite(STDOUT, $label);

    if (DIRECTORY_SEPARATOR === '\\') {
        // Windows: PowerShell's secure prompt hides the typed characters.
        // The secret travels through a pipe to this process only — never a file or the screen.
        $command = 'powershell -NoProfile -Command "'
            . '$s = Read-Host -AsSecureString; '
            . '$b = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($s); '
            . '[Console]::Out.Write([Runtime.InteropServices.Marshal]::PtrToStringBSTR($b))"';
        $value = shell_exec($command);
        if (is_string($value)) {
            return rtrim($value, "\r\n");
        }
        fwrite(STDOUT, PHP_EOL . '(A rejtett bevitel nem érhető el — a jelszó látható lesz.) ' . $label);
    } elseif (trim((string) shell_exec('stty -g 2>/dev/null')) !== '') {
        shell_exec('stty -echo 2>/dev/null');
        $value = fgets(STDIN);
        shell_exec('stty echo 2>/dev/null');
        fwrite(STDOUT, PHP_EOL);

        return rtrim((string) $value, "\r\n");
    }

    return rtrim((string) fgets(STDIN), "\r\n");
}

function prompt(string $label): string
{
    fwrite(STDOUT, $label);

    return trim((string) fgets(STDIN));
}

function fail(string $message): never
{
    fwrite(STDERR, '✗ ' . $message . PHP_EOL);
    exit(1);
}

echo PHP_EOL . 'Nyílt nap kezelő — admin fiók' . PHP_EOL . str_repeat('─', 30) . PHP_EOL;

$username = $positional[0] ?? '';
if ($username === '') {
    if (!$interactive) {
        fail('Add meg a felhasználónevet paraméterként: php scripts/create_admin.php felhasznalonev --password-stdin');
    }
    $username = prompt('Felhasználónév: ');
}
if (preg_match(AdminUser::USERNAME_PATTERN, $username) !== 1) {
    fail('A felhasználónév 3–60 karakter lehet, és csak angol betűket, számokat, pontot, kötőjelet és aláhúzást tartalmazhat.');
}

$existing = AdminUser::findForLogin($username);
if ($existing !== null && !$update) {
    if (!$interactive || $fromStdin) {
        fail("A(z) „{$username}” fiók már létezik. Jelszócseréhez add meg az --update kapcsolót.");
    }
    $answer = strtolower(prompt("A(z) „{$username}” fiók már létezik. Lecseréled a jelszavát? (i/N): "));
    if ($answer !== 'i' && $answer !== 'igen') {
        echo 'Nem történt változás.' . PHP_EOL;
        exit(0);
    }
}

if ($fromStdin) {
    // PowerShell prepends a UTF-8 BOM when piping into a program — never part of the password.
    $password = rtrim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) fgets(STDIN)), "\r\n");
} else {
    if (!$interactive) {
        fail('Nem interaktív futtatásnál használd a --password-stdin kapcsolót.');
    }
    echo 'Jelszó: legalább ' . AdminUser::PASSWORD_MIN_LENGTH . ' karakter; javasolt egy hosszú, egyedi jelmondat.' . PHP_EOL;
    $password = prompt_hidden('Jelszó: ');
    if (($problem = AdminUser::passwordProblem($password)) !== null) {
        fail($problem);
    }
    $confirm = prompt_hidden('Jelszó újra: ');
    if (!hash_equals($password, $confirm)) {
        fail('A két jelszó nem egyezik.');
    }
}

if (($problem = AdminUser::passwordProblem($password)) !== null) {
    fail($problem);
}

try {
    if ($existing !== null) {
        AdminUser::updatePassword($existing['id'], $password);
        echo "✓ A(z) „{$username}” fiók jelszava frissítve." . PHP_EOL . PHP_EOL;
    } else {
        AdminUser::create($username, $password);
        echo "✓ A(z) „{$username}” admin fiók létrehozva." . PHP_EOL;
        echo '  Bejelentkezés: /admin/login' . PHP_EOL . PHP_EOL;
    }
} catch (InvalidArgumentException $e) {
    fail($e->getMessage());
}
