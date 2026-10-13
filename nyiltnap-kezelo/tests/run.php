<?php

declare(strict_types=1);

/*
 * Automated checks — run from the project root:
 *
 *   php tests/run.php              # everything
 *   php tests/run.php --skip-lint  # skip the php -l pass
 *
 * Needs a reachable MySQL/MariaDB (credentials from .env). All database
 * work happens in TEST_DB_NAME (default: <DB_NAME>_test), whose tables are
 * dropped and rebuilt on every run. The real database is never touched.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/harness.php';

use App\Core\Database;
use App\Forms\RegistrationForm;
use App\Models\AdminUser;
use App\Models\LoginThrottle;
use App\Models\Program;
use App\Models\Registration;
use App\Models\Statistics;
use App\Support\RegistrationFilters;

$started = microtime(true);
echo 'Nyílt nap kezelő — automatikus tesztek' . PHP_EOL . str_repeat('═', 40) . PHP_EOL;

/* =====================================================================
 * 1. Syntax
 * =================================================================== */
if (!in_array('--skip-lint', $argv, true)) {
    TestRun::section('PHP szintaxis (php -l)');
    TestRun::test('minden PHP fájl hibátlanul értelmezhető', static function (): void {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(BASE_PATH, FilesystemIterator::SKIP_DOTS));
        $checked = 0;
        $broken = [];
        foreach ($files as $file) {
            $path = $file->getPathname();
            if ($file->getExtension() !== 'php' || preg_match('#[\\\\/](vendor|node_modules|\.git)[\\\\/]#', $path) === 1) {
                continue;
            }
            exec(escapeshellarg(PHP_BINARY) . ' -d display_startup_errors=0 -l ' . escapeshellarg($path) . ' 2>&1', $output, $code);
            $checked++;
            if ($code !== 0) {
                $broken[] = $path;
            }
        }
        check($broken === [], 'syntax errors in: ' . implode(', ', $broken));
        check($checked > 40, "only {$checked} files found");
    });
}

/* =====================================================================
 * 2. Schema
 * =================================================================== */
TestRun::section('Adatbázis-séma');
$testDb = TestDatabase::prepare();
echo "  (tesztadatbázis: {$testDb})" . PHP_EOL;
$p = TestDatabase::programIds();

TestRun::test('a séma és a szakmák importja sikeres és idempotens', static function (): void {
    App\Support\SqlFile::run(Database::connection(), BASE_PATH . '/database/schema.sql');
    App\Support\SqlFile::run(Database::connection(), BASE_PATH . '/database/seed.sql');
    check_same(6, TestDatabase::count('programs'), 'program count after re-import');
    foreach (['programs', 'registrations', 'registration_programs', 'admin_users', 'login_attempts'] as $table) {
        $row = Database::connection()->query("SHOW TABLE STATUS LIKE '{$table}'")->fetch();
        check_same('InnoDB', $row['Engine'], "{$table} engine");
        check_same('utf8mb4_unicode_ci', $row['Collation'], "{$table} collation");
    }
});

TestRun::test('a név nem egyedi; csak az id és az azonosító az', static function (): void {
    $unique = [];
    foreach (Database::connection()->query('SHOW INDEX FROM registrations')->fetchAll() as $index) {
        if ((int) $index['Non_unique'] === 0) {
            $unique[] = $index['Column_name'];
        }
    }
    sort($unique);
    check_same(['id', 'reference_code'], $unique, 'unique columns');
});

/* =====================================================================
 * 3. Form validation
 * =================================================================== */
TestRun::section('Űrlap-ellenőrzés (szerveroldal)');
$active = Program::activeById();
$valid = [
    'full_name'      => '  Kovács    Anna ',
    'current_school' => 'Példa Utcai Általános Iskola',
    'programs'       => [(string) $p['informatika'], (string) $p['gepeszet'], (string) $p['informatika']],
    'residency'      => 'boarding',
    'graduating'     => '1',
];

TestRun::test('érvényes adat elfogadva, szóközök normalizálva, ismétlődő szakma összevonva', static function () use ($active, $valid, $p): void {
    $form = new RegistrationForm($active);
    check($form->validate($valid), 'should be valid: ' . json_encode($form->errors(), JSON_UNESCAPED_UNICODE));
    $data = $form->data();
    check_same('Kovács Anna', $data['full_name']);
    check_same([$p['informatika'], $p['gepeszet']], $data['program_ids']);
    check_same(true, $data['is_graduating']);
});

TestRun::test('magyar és nemzetközi nevek elfogadva', static function () use ($active, $valid): void {
    foreach (['Ősz Ünige', 'Szőke-Nagy Örs', "O'Brien Áron", 'Łukasz Żółć', 'Dr. Kiss Éva', 'Nguyễn Văn An'] as $name) {
        $form = new RegistrationForm($active);
        check($form->validate(['full_name' => $name] + $valid), "rejected valid name: {$name}");
    }
});

$invalidCases = [
    'üres név'                       => [['full_name' => ''], 'full_name'],
    'csak szóköz'                    => [['full_name' => "   \t "], 'full_name'],
    'túl rövid név'                  => [['full_name' => 'A'], 'full_name'],
    'túl hosszú név (121)'           => [['full_name' => str_repeat('á', 121)], 'full_name'],
    'számjegy a névben'              => [['full_name' => 'Anna 2'], 'full_name'],
    'HTML a névben'                  => [['full_name' => '<b>Anna</b>'], 'full_name'],
    'érvénytelen UTF-8'              => [['full_name' => "Kov\xC3\x28cs"], 'full_name'],
    'vezérlőkarakter'                => [['full_name' => "Anna\u{200E}Kiss"], 'full_name'],
    'tömb a név helyén'              => [['full_name' => ['x']], 'full_name'],
    'üres iskola'                    => [['current_school' => ' '], 'current_school'],
    'túl hosszú iskola (181)'        => [['current_school' => str_repeat('b', 181)], 'current_school'],
    'iskola betű nélkül'             => [['current_school' => '123'], 'current_school'],
    'nincs szakma'                   => [['programs' => []], 'programs'],
    'szakma nem tömb'                => [['programs' => '1'], 'programs'],
    'nem létező szakma-id'           => [['programs' => ['999999']], 'programs'],
    'SQL a szakma-id-ben'            => [['programs' => ['1 OR 1=1']], 'programs'],
    'negatív szakma-id'              => [['programs' => ['-1']], 'programs'],
    'vezető nullás szakma-id'        => [['programs' => ['01']], 'programs'],
    'érvénytelen lakhatás'           => [['residency' => 'both'], 'residency'],
    'hiányzó lakhatás'               => [['residency' => null], 'residency'],
    'érvénytelen végzős érték'       => [['graduating' => 'yes'], 'graduating'],
    'végzős tömbként'                => [['graduating' => ['1']], 'graduating'],
];
foreach ($invalidCases as $label => [$override, $field]) {
    TestRun::test("elutasítva: {$label}", static function () use ($active, $valid, $override, $field): void {
        $form = new RegistrationForm($active);
        check(!$form->validate(array_merge($valid, $override)), 'should be invalid');
        check(isset($form->errors()[$field]), "expected an error for {$field}, got: " . implode(', ', array_keys($form->errors())));
    });
}

TestRun::test('inaktív szakma nem választható, de a többi bejelölés megmarad', static function () use ($valid, $p): void {
    Database::connection()->exec('UPDATE programs SET is_active = 0 WHERE id = ' . $p['energetika']);
    $form = new RegistrationForm(Program::activeById());
    $ok = $form->validate(['programs' => [(string) $p['energetika'], (string) $p['gepeszet']]] + $valid);
    Database::connection()->exec('UPDATE programs SET is_active = 1');
    check(!$ok, 'inactive program must be rejected');
    check_same([$p['gepeszet']], $form->old()['programs'], 'valid ticks kept for re-render');
});

TestRun::test('nincs aktív szakma: érthető hibaüzenet', static function () use ($valid): void {
    $form = new RegistrationForm([]);
    check(!$form->validate($valid), 'must fail');
    check_contains('nincs választható szakma', $form->errors()['programs'] ?? '');
});

/* =====================================================================
 * 4. Persistence & statistics
 * =================================================================== */
TestRun::section('Mentés, tranzakciók, statisztika');
TestDatabase::clear();

TestRun::test('jelentkezés több szakmával egy tranzakcióban mentve', static function () use ($p): void {
    $saved = Registration::create('Teszt Elek', 'Minta Iskola', 'boarding', true, [$p['informatika'], $p['elektronika']]);
    check(preg_match('/^[A-HJKMNP-Z2-9]{8}$/', $saved['reference']) === 1, 'reference format: ' . $saved['reference']);
    $row = Registration::find($saved['id']);
    check_same(2, count($row['programs']), 'program links');
    check_same('boarding', $row['residency_preference']);
    check_same(true, $row['is_graduating']);
});

TestRun::test('azonos nevek (és teljesen azonos adatok) is rögzíthetők', static function () use ($p): void {
    $before = TestDatabase::count('registrations');
    Registration::create('Kovács Anna', 'Minta Iskola', 'commuter', false, [$p['gepeszet']]);
    Registration::create('Kovács Anna', 'Minta Iskola', 'commuter', false, [$p['gepeszet']]);
    check_same($before + 2, TestDatabase::count('registrations'));
});

TestRun::test('hibás kapcsolótábla-írásnál a teljes jelentkezés visszagörgetve', static function () use ($p): void {
    $registrations = TestDatabase::count('registrations');
    $links = TestDatabase::count('registration_programs');
    $thrown = false;
    try {
        // Bypasses the validator on purpose: the 2nd link violates the foreign key.
        Registration::create('Rollback Rózsa', 'Minta Iskola', 'boarding', true, [$p['informatika'], 987654]);
    } catch (PDOException) {
        $thrown = true;
    }
    check($thrown, 'a PDOException was expected');
    check_same($registrations, TestDatabase::count('registrations'), 'registrations unchanged');
    check_same($links, TestDatabase::count('registration_programs'), 'links unchanged');
});

TestRun::test('statisztika: összesítők, szakmánkénti és kollégium × végzős bontás pontos', static function () use ($p): void {
    TestDatabase::clear();
    // name, residency, graduating, programs
    $dataset = [
        ['A Anna',  'boarding', true,  ['informatika', 'gepeszet']],
        ['B Béla',  'commuter', true,  ['informatika']],
        ['C Cecil', 'boarding', false, ['informatika', 'elektronika']],
        ['D Dóra',  'commuter', false, ['gepeszet']],
        ['E Emma',  'boarding', true,  ['elektronika']],
        ['A Anna',  'boarding', false, ['informatika', 'gepeszet', 'elektronika']],
    ];
    foreach ($dataset as [$name, $residency, $graduating, $slugs]) {
        Registration::create($name, 'Minta Iskola', $residency, $graduating, array_map(static fn ($s) => $p[$s], $slugs));
    }

    $o = Statistics::overview();
    check_same(6, $o['total'], 'total must not be inflated by the join (10 links)');
    check_same(3, $o['graduating'], 'graduating');
    check_same(3, $o['non_graduating'], 'non graduating');
    check_same(4, $o['boarding'], 'boarding');
    check_same(2, $o['commuter'], 'commuter');
    check_same(2, $o['boarding_graduating'], 'boarding + graduating');
    check_same(2, $o['boarding_non_graduating'], 'boarding + non graduating');
    check_same(1, $o['commuter_graduating'], 'commuter + graduating');
    check_same(1, $o['commuter_non_graduating'], 'commuter + non graduating');
    check_same(10, Statistics::programSelections(), 'program ticks');
    check_same(3, Statistics::programsRepresented(), 'programs represented');

    $byId = array_column(Statistics::byProgram(), null, 'id');
    $expect = [
        // slug => [total, graduating, boarding, boarding_grad, boarding_non_grad, commuter]
        'informatika' => [4, 2, 3, 1, 2, 1],
        'gepeszet'    => [3, 1, 2, 1, 1, 1],
        'elektronika' => [3, 1, 3, 1, 2, 0],
        'mechatronika' => [0, 0, 0, 0, 0, 0],
    ];
    foreach ($expect as $slug => [$total, $grad, $boarding, $bg, $bn, $commuter]) {
        $row = $byId[$p[$slug]];
        check_same($total, $row['total'], "{$slug} total");
        check_same($grad, $row['graduating'], "{$slug} graduating");
        check_same($total - $grad, $row['non_graduating'], "{$slug} non graduating");
        check_same($boarding, $row['boarding'], "{$slug} boarding");
        check_same($bg, $row['boarding_graduating'], "{$slug} boarding + graduating");
        check_same($bn, $row['boarding_non_graduating'], "{$slug} boarding + non graduating");
        check_same($commuter, $row['commuter'], "{$slug} commuter");
    }
    check_same(6, count($byId), 'all active programs listed, even with zero');
});

TestRun::test('szakma-szűrő nem duplikál sorokat; szűretlen lista = jelentkezők száma', static function () use ($p): void {
    check_same(6, Registration::count(new RegistrationFilters()));
    check_same(6, count(Registration::search(new RegistrationFilters(), 100)));
    $f = RegistrationFilters::fromQuery(['program' => (string) $p['informatika']], array_values($p));
    check_same(4, Registration::count($f));
});

TestRun::test('napi bontás az alkalmazás időzónájában', static function () use ($p): void {
    TestDatabase::clear();
    Registration::create('Napi Egy', 'Minta Iskola', 'boarding', true, [$p['informatika']], '2026-10-01 09:00:00');
    Registration::create('Napi Kettő', 'Minta Iskola', 'boarding', false, [$p['informatika']], '2026-10-01 23:59:00');
    Registration::create('Napi Három', 'Minta Iskola', 'commuter', true, [$p['gepeszet']], '2026-10-02 00:01:00');
    check_same([
        ['day' => '2026-10-01', 'total' => 2, 'graduating' => 1],
        ['day' => '2026-10-02', 'total' => 1, 'graduating' => 1],
    ], Statistics::daily());
});

TestRun::test('üres adatbázis: nullás statisztika hiba nélkül', static function (): void {
    TestDatabase::clear();
    $o = Statistics::overview();
    check_same(0, $o['total']);
    check_same(null, $o['latest_at']);
    check_same([], Statistics::daily());
    check_same(6, count(Statistics::byProgram()));
});

/* =====================================================================
 * 5. Filters, search, pagination, deletion
 * =================================================================== */
TestRun::section('Szűrés, keresés, lapozás, törlés');
TestDatabase::clear();
$schools = ['Minta Iskola', '100% Példa Iskola', 'Iskola_X Példa', "O'Neill Példa Iskola"];
for ($i = 1; $i <= 30; $i++) {
    Registration::create(
        $i === 7 ? 'Kovács Ádám' : 'Diák ' . str_repeat('a', $i % 5 + 1) . 'bc',
        $schools[$i % 4],
        $i % 3 === 0 ? 'boarding' : 'commuter',
        $i % 2 === 0,
        $i % 5 === 0 ? [$p['informatika'], $p['gepeszet']] : [$p['gepeszet']],
    );
}

TestRun::test('lapozás: 25/oldal, 2. oldalon 5 sor, túl nagy oldalszám az utolsóra igazítva', static function (): void {
    $f = RegistrationFilters::fromQuery(['page' => '2'], []);
    check_same(2, $f->clampPage(Registration::count($f)));
    check_same(5, count(Registration::search($f, $f->perPage, $f->offset())));
    $far = RegistrationFilters::fromQuery(['page' => '999'], []);
    $far->clampPage(30);
    check_same(2, $far->page);
});

TestRun::test('kombinált szűrők helyesen összeadódnak', static function () use ($p): void {
    // Expected by construction: i%5==0 → informatika; i%2==0 → graduating; i%3==0 → boarding.
    $expected = count(array_filter(range(1, 30), static fn ($i) => $i % 5 === 0 && $i % 2 === 0 && $i % 3 === 0));
    $f = RegistrationFilters::fromQuery(['program' => (string) $p['informatika'], 'graduating' => 'yes', 'residency' => 'boarding'], array_values($p));
    check_same($expected, Registration::count($f));
    check_same(1, $expected);

    $grad = RegistrationFilters::fromQuery(['graduating' => 'no', 'residency' => 'commuter'], []);
    $expected = count(array_filter(range(1, 30), static fn ($i) => $i % 2 !== 0 && $i % 3 !== 0));
    check_same($expected, Registration::count($grad));
});

TestRun::test('keresés: % és _ szó szerint, ékezetfüggetlen, aposztróf biztonságos', static function (): void {
    $count = static fn (string $q): int => Registration::count(RegistrationFilters::fromQuery(['q' => $q], []));
    check_same(8, $count('%'), 'only "100% …" schools');
    check_same(8, $count('_'), 'only "Iskola_X …" schools');
    check_same(1, $count('kovacs adam'), 'accent-insensitive');
    check_same(1, $count('KOVÁCS'), 'case-insensitive');
    check_same(7, $count("O'Neill"), 'apostrophe');
});

TestRun::test('SQL injection kísérlet adat marad, nem módosítja a lekérdezést', static function (): void {
    check_same(0, Registration::count(RegistrationFilters::fromQuery(['q' => "' OR '1'='1"], [])));
    check_same(0, Registration::count(RegistrationFilters::fromQuery(['q' => '"; DROP TABLE registrations; --'], [])));
    $f = RegistrationFilters::fromQuery(['sort' => 'id; DROP TABLE registrations', 'per_page' => '1000', 'page' => '-3', 'graduating' => 'maybe'], []);
    check_same('newest', $f->sort);
    check_same(25, $f->perPage);
    check_same(1, $f->page);
    check_same(null, $f->graduating);
    check_same(30, TestDatabase::count('registrations'), 'table intact');
});

TestRun::test('érvénytelen szakma-szűrő figyelmen kívül hagyva és jelezve', static function () use ($p): void {
    $f = RegistrationFilters::fromQuery(['program' => '99999'], array_values($p));
    check($f->invalidProgram && $f->programId === null, 'invalid program flagged');
    $g = RegistrationFilters::fromQuery(['program' => ['x']], array_values($p));
    check($g->invalidProgram, 'array value flagged');
});

TestRun::test('törlés: hibás azonosítóval nem töröl, helyessel töröl, kapcsolók kaszkádolva, szakma marad', static function () use ($p): void {
    $saved = Registration::create('Törlendő Tamás', 'Minta Iskola', 'boarding', true, [$p['informatika'], $p['gepeszet']]);
    $programs = TestDatabase::count('programs');
    check_same(Registration::MISMATCH, Registration::delete($saved['id'], 'AAAAAAAA'));
    check(Registration::find($saved['id']) !== null, 'must still exist after mismatch');
    check_same(Registration::DELETED, Registration::delete($saved['id'], $saved['reference']));
    check_same(null, Registration::find($saved['id']));
    $links = (int) Database::connection()->query('SELECT COUNT(*) FROM registration_programs WHERE registration_id = ' . $saved['id'])->fetchColumn();
    check_same(0, $links, 'join rows cascaded');
    check_same($programs, TestDatabase::count('programs'), 'programs untouched');
    check_same(Registration::NOT_FOUND, Registration::delete($saved['id'], $saved['reference']), 'stale delete');
});

/* =====================================================================
 * 6. Security helpers
 * =================================================================== */
TestRun::section('Biztonsági segédfüggvények');

TestRun::test('kimenet escapelése (XSS)', static function (): void {
    $escaped = e('<script>alert("x")</script> & \'');
    check_not_contains('<', $escaped);
    check_not_contains('"', $escaped);
    check_not_contains("'", $escaped);
    check_same('&lt;b&gt;', e('<b>'));
    check_same('', e(null));
});

TestRun::test('átirányítási cél csak az adminfelületen belül lehet (open redirect)', static function (): void {
    foreach (['//evil.com', 'https://evil.com', '/\\evil.com', '/admin/../etc', 'javascript:alert(1)', '/admin//evil', '/admin/logout', '/admin/login?next=x', '', null, ['x']] as $bad) {
        check_same('/admin', safe_admin_path($bad), 'should reject ' . var_export($bad, true));
    }
    check_same('/admin/registrations?q=kov%C3%A1cs&page=2', safe_admin_path('/admin/registrations?q=kov%C3%A1cs&page=2'));
    check_same('/admin/statistics', safe_admin_path('/admin/statistics'));
});

TestRun::test('bejelentkezés-korlátozás: 5 hiba után ideiglenes tiltás, törlés után feloldás', static function (): void {
    $ip = '203.0.113.' . random_int(1, 254);
    LoginThrottle::clear($ip);
    for ($i = 0; $i < 4; $i++) {
        LoginThrottle::recordFailure($ip);
    }
    check_same(0, LoginThrottle::secondsUntilAllowed($ip), 'still allowed after 4');
    LoginThrottle::recordFailure($ip);
    $wait = LoginThrottle::secondsUntilAllowed($ip);
    check($wait > 0 && $wait <= 15 * 60, "blocked for a bounded time (got {$wait}s)");
    LoginThrottle::clear($ip);
    check_same(0, LoginThrottle::secondsUntilAllowed($ip));
});

TestRun::test('jelszó: hash tárolva, rövid jelszó elutasítva', static function (): void {
    $id = AdminUser::create('hash.check', 'egy-hosszu-jelszo');
    $row = AdminUser::findForLogin('hash.check');
    check($row !== null && $row['id'] === $id, 'created');
    check(str_starts_with($row['password_hash'], '$2y$') || str_starts_with($row['password_hash'], '$argon2'), 'hashed');
    check(password_verify('egy-hosszu-jelszo', $row['password_hash']), 'verifies');
    $thrown = false;
    try {
        AdminUser::create('short.pw', 'rovid');
    } catch (InvalidArgumentException) {
        $thrown = true;
    }
    check($thrown, 'short password rejected');
});

/* =====================================================================
 * 7. End-to-end over HTTP (real php -S server against the test DB)
 * =================================================================== */
TestRun::section('Végponttól végpontig (HTTP)');
TestDatabase::clear();
AdminUser::create('tester', 'teszt-jelszo-2026');

$server = new TestServer();
$server->start([
    'DB_NAME'        => $testDb,
    'APP_ENV'        => 'testing',
    'APP_DEBUG'      => 'false',
    'SESSION_SECURE' => 'false',
]);

try {
    $student = new HttpClient($server->base);
    $formFields = static function (HttpResponse $page, array $overrides = []) use ($p): array {
        return array_merge([
            '_csrf'          => $page->csrf(),
            '_form_token'    => $page->formToken(),
            'full_name'      => 'Szabó Zsófia',
            'current_school' => 'Példa Utcai Általános Iskola',
            'programs'       => [(string) $p['informatika'], (string) $p['mechatronika']],
            'residency'      => 'boarding',
            'graduating'     => '1',
        ], $overrides);
    };

    TestRun::test('GET / → űrlap, CSRF- és űrlaptoken, biztonsági fejlécek', static function () use ($student): void {
        $r = $student->get('/');
        check_same(200, $r->status);
        check_contains('Jelentkezés elküldése', $r->body);
        check($r->csrf() !== '' && $r->formToken() !== '', 'tokens present');
        check_contains("default-src 'self'", $r->header('Content-Security-Policy'));
        check_same('nosniff', $r->header('X-Content-Type-Options'));
        check_same('DENY', $r->header('X-Frame-Options'));
        check_contains('HttpOnly', $r->header('Set-Cookie'));
        check_contains('SameSite=Lax', $r->header('Set-Cookie'));
    });

    TestRun::test('POST / CSRF-token nélkül → 403, nincs mentés, adatok megmaradnak', static function () use ($student, $formFields): void {
        $before = TestDatabase::count('registrations');
        $fields = $formFields($student->get('/'), ['_csrf' => 'hamis']);
        $r = $student->post('/', $fields);
        check_same(403, $r->status);
        check_contains('Szabó Zsófia', $r->body, 'value preserved');
        check_same($before, TestDatabase::count('registrations'));
    });

    TestRun::test('érvényes beküldés → 303 /sikeres (PRG), frissítés nem ment újra', static function () use ($student, $formFields): void {
        $before = TestDatabase::count('registrations');
        $r = $student->post('/', $formFields($student->get('/')));
        check_same(303, $r->status);
        check(str_ends_with($r->header('Location'), '/sikeres'), 'redirect target: ' . $r->header('Location'));
        check_same($before + 1, TestDatabase::count('registrations'));

        $success = $student->get('/sikeres');
        check_same(200, $success->status);
        check_contains('Sikeres regisztráció', $success->body);
        check_contains('Szabó Zsófia', $success->body);
        $student->get('/sikeres'); // refresh
        check_same($before + 1, TestDatabase::count('registrations'), 'refresh must not insert');
        check_same(2, (int) Database::connection()->query('SELECT COUNT(*) FROM registration_programs')->fetchColumn(), 'two program links');
    });

    TestRun::test('ugyanannak az űrlapnak a dupla beküldése nem hoz létre második sort', static function () use ($student, $formFields): void {
        $page = $student->get('/');
        $fields = $formFields($page, ['full_name' => 'Dupla Dénes']);
        $first = $student->post('/', $fields);
        $second = $student->post('/', $fields);
        check_same(303, $first->status);
        check_same(303, $second->status);
        $n = (int) Database::connection()->query("SELECT COUNT(*) FROM registrations WHERE full_name = 'Dupla Dénes'")->fetchColumn();
        check_same(1, $n);
    });

    TestRun::test('új űrlappal azonos adatok újra beküldhetők (azonos név engedélyezett)', static function () use ($student, $formFields): void {
        $before = TestDatabase::count('registrations');
        $student->post('/', $formFields($student->get('/')));
        check_same($before + 1, TestDatabase::count('registrations'));
        $n = (int) Database::connection()->query("SELECT COUNT(*) FROM registrations WHERE full_name = 'Szabó Zsófia'")->fetchColumn();
        check_same(2, $n);
    });

    TestRun::test('hamisított szakma-id → 422, mezőszintű magyar hiba, nincs mentés', static function () use ($student, $formFields): void {
        $before = TestDatabase::count('registrations');
        $r = $student->post('/', $formFields($student->get('/'), ['programs' => ['424242'], 'full_name' => 'Hamis Hanna']));
        check_same(422, $r->status);
        check_contains('érvénytelen elem', $r->body);
        check_contains('value="Hamis Hanna"', $r->body, 'name preserved');
        check_same($before, TestDatabase::count('registrations'));
    });

    TestRun::test('XSS-kísérlet adatként tárolva és escapelve jelenik meg', static function () use ($student, $formFields): void {
        $payload = '<script>alert(1)</script> Iskola';
        $r = $student->post('/', $formFields($student->get('/'), ['current_school' => $payload, 'full_name' => 'Xss Xénia']));
        check_same(303, $r->status);
        $success = $student->get('/sikeres');
        check_not_contains($payload, $success->body);
        check_contains('&lt;script&gt;alert(1)&lt;/script&gt; Iskola', $success->body);
    });

    $anon = new HttpClient($server->base);
    TestRun::test('névtelen látogató nem éri el az admin oldalakat (302 → login)', static function () use ($anon): void {
        foreach (['/admin', '/admin/dashboard', '/admin/registrations', '/admin/statistics', '/admin/registrations/export', '/admin/registrations/1/delete'] as $path) {
            $r = $anon->get($path);
            check_same(302, $r->status, $path);
            check_contains('/admin/login?next=', $r->header('Location'), $path);
        }
    });

    TestRun::test('névtelen POST törlés → 403, a sor megmarad', static function () use ($anon): void {
        $id = (int) Database::connection()->query('SELECT MIN(id) FROM registrations')->fetchColumn();
        $before = TestDatabase::count('registrations');
        $r = $anon->post("/admin/registrations/{$id}/delete", ['reference' => 'x']);
        check_same(403, $r->status);
        check_same($before, TestDatabase::count('registrations'));
    });

    TestRun::test('hibás jelszó → általános hibaüzenet, nincs felhasználó-felsorolás', static function () use ($anon): void {
        $page = $anon->get('/admin/login');
        $wrongPass = $anon->post('/admin/login', ['_csrf' => $page->csrf(), 'username' => 'tester', 'password' => 'rossz-jelszo', 'next' => '/admin']);
        $noUser = $anon->post('/admin/login', ['_csrf' => $page->csrf(), 'username' => 'nincs.ilyen', 'password' => 'rossz-jelszo', 'next' => '/admin']);
        check_same(422, $wrongPass->status);
        check_same(422, $noUser->status);
        check_contains('Hibás felhasználónév vagy jelszó.', $wrongPass->body);
        check_contains('Hibás felhasználónév vagy jelszó.', $noUser->body);
        Database::connection()->exec('DELETE FROM login_attempts');
    });

    $admin = new HttpClient($server->base);
    TestRun::test('sikeres belépés: új munkamenet-azonosító, külső next figyelmen kívül', static function () use ($admin): void {
        $page = $admin->get('/admin/login?next=//evil.example');
        $before = $admin->sessionId();
        $r = $admin->post('/admin/login', ['_csrf' => $page->csrf(), 'username' => 'tester', 'password' => 'teszt-jelszo-2026', 'next' => '//evil.example']);
        check_same(303, $r->status);
        check(str_ends_with($r->header('Location'), '/admin'), 'redirected inside admin: ' . $r->header('Location'));
        check($admin->sessionId() !== '' && $admin->sessionId() !== $before, 'session id regenerated');
    });

    TestRun::test('admin oldalak elérhetők, adatbázisból számolt értékekkel', static function () use ($admin): void {
        $total = TestDatabase::count('registrations');
        $dash = $admin->get('/admin');
        check_same(200, $dash->status);
        check_contains('Nyílt napi jelentkezések', $dash->body);
        check_contains('<dd class="kpi__value">' . $total . '</dd>', $dash->body, 'KPI total');
        check_same('noindex, nofollow', $dash->header('X-Robots-Tag'));
        check_same(200, $admin->get('/admin/statistics')->status);
        $list = $admin->get('/admin/registrations');
        check_same(200, $list->status);
        check_contains('&lt;script&gt;alert(1)&lt;/script&gt;', $list->body, 'escaped in admin list');
        check_not_contains('<script>alert(1)</script>', $list->body);
    });

    TestRun::test('lapozás és szűrők HTTP-n: túl nagy oldalszám kezelve, szűrők a linkekben megmaradnak', static function () use ($admin, $p): void {
        $r = $admin->get('/admin/registrations?page=999&residency=boarding&graduating=yes&program=' . $p['informatika']);
        check_same(200, $r->status);
        check_contains('találat', $r->body);
        $csv = $admin->get('/admin/registrations/export?residency=boarding');
        check_same(200, $csv->status);
    });

    TestRun::test('CSV export: UTF-8 BOM, pontosvessző, képlet-injekció semlegesítve', static function () use ($admin, $p): void {
        Registration::create('Képlet Kató', '=HYPERLINK("http://evil")', 'commuter', false, [$p['gepeszet']]);
        $r = $admin->get('/admin/registrations/export?q=K%C3%A9plet');
        check_same(200, $r->status);
        check_contains('text/csv', $r->header('Content-Type'));
        check_contains('attachment', $r->header('Content-Disposition'));
        check(str_starts_with($r->body, "\xEF\xBB\xBF"), 'BOM');
        check_contains('Azonosító;"Teljes név";"Jelenlegi iskola";Szakmák;', $r->body, 'semicolon-separated header');
        check_contains("'=HYPERLINK", $r->body);
    });

    TestRun::test('törlés GET-tel csak megerősítő oldalt ad, nem töröl', static function () use ($admin): void {
        $id = (int) Database::connection()->query("SELECT id FROM registrations WHERE full_name = 'Xss Xénia'")->fetchColumn();
        $r = $admin->get("/admin/registrations/{$id}/delete");
        check_same(200, $r->status);
        check_contains('Végleges törlés', $r->body);
        check(Registration::find($id) !== null, 'still exists');
        check_same(404, $admin->get('/admin/registrations/999999/delete')->status, 'unknown id → 404');
    });

    TestRun::test('törlés: CSRF nélkül 403, rossz azonosítóval nem töröl, helyesen töröl, másodszor „már nem létezik”', static function () use ($admin): void {
        $row = Database::connection()->query("SELECT id, reference_code FROM registrations WHERE full_name = 'Xss Xénia'")->fetch();
        $id = (int) $row['id'];
        $csrf = $admin->get('/admin/registrations')->csrf();

        check_same(403, $admin->post("/admin/registrations/{$id}/delete", ['reference' => $row['reference_code']])->status);
        check(Registration::find($id) !== null, 'survives missing CSRF');

        $mismatch = $admin->post("/admin/registrations/{$id}/delete", ['_csrf' => $csrf, 'reference' => 'ZZZZZZZZ', 'return' => '/admin/registrations']);
        check_same(303, $mismatch->status);
        check(Registration::find($id) !== null, 'survives reference mismatch');
        check_contains('nem egyeznek', $admin->get('/admin/registrations')->body);

        $ok = $admin->post("/admin/registrations/{$id}/delete", ['_csrf' => $csrf, 'reference' => $row['reference_code'], 'return' => '/admin/registrations?graduating=yes']);
        check_same(303, $ok->status);
        check(str_ends_with($ok->header('Location'), '/admin/registrations?graduating=yes'), 'returns to filtered list');
        check_same(null, Registration::find($id));
        check_contains('törölve', $admin->get('/admin/registrations?graduating=yes')->body, 'success flash');

        $stale = $admin->post("/admin/registrations/{$id}/delete", ['_csrf' => $csrf, 'reference' => $row['reference_code'], 'return' => 'https://evil.example']);
        check_same(303, $stale->status);
        check(str_ends_with($stale->header('Location'), '/admin/registrations'), 'unsafe return replaced');
        check_contains('már nem létezik', $admin->get('/admin/registrations')->body);
    });

    TestRun::test('kijelentkezés csak POST + CSRF; utána az admin újra zárt', static function () use ($admin): void {
        check_same(405, $admin->get('/admin/logout')->status, 'GET logout');
        check_same(403, $admin->post('/admin/logout', ['_csrf' => 'hamis'])->status);
        $csrf = $admin->get('/admin')->csrf();
        $r = $admin->post('/admin/logout', ['_csrf' => $csrf]);
        check_same(303, $r->status);
        check_same(302, $admin->get('/admin')->status, 'locked again');
    });

    TestRun::test('HTTP státuszkódok: 404, 405 + Allow, hibaoldalon nincs belső részlet', static function () use ($anon): void {
        $missing = $anon->get('/nincs-ilyen-oldal');
        check_same(404, $missing->status);
        check_contains('Az oldal nem található', $missing->body);
        check_not_contains('Fejlesztői részletek', $missing->body);
        $method = $anon->send('DELETE', '/admin');
        check_same(405, $method->status);
        check_contains('GET', $method->header('Allow'));
    });

    TestRun::test('brute-force: 5 hibás próbálkozás után 429 + Retry-After', static function () use ($server): void {
        Database::connection()->exec('DELETE FROM login_attempts');
        $attacker = new HttpClient($server->base);
        $csrf = $attacker->get('/admin/login')->csrf();
        for ($i = 0; $i < 5; $i++) {
            $attacker->post('/admin/login', ['_csrf' => $csrf, 'username' => 'tester', 'password' => 'rossz' . $i]);
        }
        $blocked = $attacker->post('/admin/login', ['_csrf' => $csrf, 'username' => 'tester', 'password' => 'teszt-jelszo-2026']);
        check_same(429, $blocked->status, 'even the right password waits');
        check((int) $blocked->header('Retry-After') > 0, 'Retry-After header');
        Database::connection()->exec('DELETE FROM login_attempts');
    });
} finally {
    $server->stop();
}

TestRun::test('elérhetetlen adatbázis → 503 barátságos oldal, hitelesítő adat nélkül', static function () use ($testDb): void {
    $down = new TestServer();
    $down->start(['DB_NAME' => $testDb, 'DB_PORT' => '1', 'DB_HOST' => '127.0.0.1', 'APP_DEBUG' => 'false']);
    try {
        $r = (new HttpClient($down->base))->get('/');
        check_same(503, $r->status);
        check_contains('Átmenetileg nem elérhető', $r->body);
        check_not_contains('SQLSTATE', $r->body);
        check_not_contains('PDOException', $r->body);
        check_not_contains('Access denied', $r->body);
        check_not_contains('127.0.0.1:1', $r->body);
    } finally {
        $down->stop();
    }
});

/* =====================================================================
 * Summary
 * =================================================================== */
TestDatabase::clear();
$seconds = number_format(microtime(true) - $started, 1);
echo PHP_EOL . str_repeat('─', 40) . PHP_EOL;
echo 'Eredmény: ' . TestRun::$passed . ' sikeres, ' . TestRun::$failed . " sikertelen ({$seconds} s)" . PHP_EOL;
if (TestRun::$failed > 0) {
    echo 'Sikertelen: ' . implode('; ', TestRun::$failures) . PHP_EOL;
    exit(1);
}
exit(0);
