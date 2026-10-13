<?php

declare(strict_types=1);

/*
 * Tiny dependency-free test harness: assertions, a disposable test
 * database and an HTTP client that talks to a real `php -S` server.
 */

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Support\SqlFile;

final class AssertionFailed extends RuntimeException
{
}

final class TestRun
{
    public static int $passed = 0;
    public static int $failed = 0;
    /** @var list<string> */
    public static array $failures = [];

    public static function section(string $title): void
    {
        echo PHP_EOL . $title . PHP_EOL;
    }

    public static function test(string $name, callable $body): void
    {
        try {
            $body();
            self::$passed++;
            echo "  \u{2713} {$name}" . PHP_EOL;
        } catch (Throwable $e) {
            self::$failed++;
            self::$failures[] = $name;
            $where = $e instanceof AssertionFailed ? '' : ' [' . $e::class . ' @ ' . basename($e->getFile()) . ':' . $e->getLine() . ']';
            echo "  \u{2717} {$name}" . PHP_EOL . '      ' . $e->getMessage() . $where . PHP_EOL;
        }
    }
}

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new AssertionFailed($message);
    }
}

function check_same(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new AssertionFailed(($message !== '' ? $message . ': ' : '') . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function check_contains(string $needle, string $haystack, string $message = ''): void
{
    if (!str_contains($haystack, $needle)) {
        throw new AssertionFailed(($message !== '' ? $message . ': ' : '') . 'missing "' . $needle . '"');
    }
}

function check_not_contains(string $needle, string $haystack, string $message = ''): void
{
    if (str_contains($haystack, $needle)) {
        throw new AssertionFailed(($message !== '' ? $message . ': ' : '') . 'unexpected "' . $needle . '"');
    }
}

/* ---------------------------------------------------------------------
 * Disposable test database
 * ------------------------------------------------------------------- */

final class TestDatabase
{
    /**
     * Points the app at TEST_DB_NAME and rebuilds its tables from the
     * project's schema + seed. Refuses to run unless the name ends in
     * "_test", so a real database can never be wiped by accident.
     */
    public static function prepare(): string
    {
        $name = (string) Env::get('TEST_DB_NAME', Config::get('db.name') . '_test');
        if (preg_match('/^[A-Za-z0-9_]{1,59}_test$/', $name) !== 1) {
            throw new RuntimeException("TEST_DB_NAME must end with \"_test\" (got \"{$name}\").");
        }
        if ($name === Config::get('db.name')) {
            throw new RuntimeException('TEST_DB_NAME must differ from DB_NAME.');
        }

        $config = (array) Config::get('db');
        $server = Database::connect($config, false);
        $server->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $server = null;

        Config::set('db.name', $name);
        $pdo = Database::connect((array) Config::get('db'), true);
        Database::setConnection($pdo);

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['registration_programs', 'registrations', 'programs', 'admin_users', 'login_attempts'] as $table) {
            $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        SqlFile::run($pdo, BASE_PATH . '/database/schema.sql');
        SqlFile::run($pdo, BASE_PATH . '/database/seed.sql');

        return $name;
    }

    /** Removes applicants, admins and login attempts; keeps the seeded programs. */
    public static function clear(): void
    {
        $pdo = Database::connection();
        $pdo->exec('DELETE FROM registration_programs');
        $pdo->exec('DELETE FROM registrations');
        $pdo->exec('DELETE FROM admin_users');
        $pdo->exec('DELETE FROM login_attempts');
        $pdo->exec('UPDATE programs SET is_active = 1');
    }

    public static function count(string $table): int
    {
        return (int) Database::connection()->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    }

    /** @return array<string, int> slug => id */
    public static function programIds(): array
    {
        $ids = [];
        foreach (Database::connection()->query('SELECT id, slug FROM programs')->fetchAll() as $row) {
            $ids[(string) $row['slug']] = (int) $row['id'];
        }

        return $ids;
    }
}

/* ---------------------------------------------------------------------
 * HTTP: a real built-in server and a cookie-keeping client
 * ------------------------------------------------------------------- */

final class TestServer
{
    /** @var resource|null */
    private $process = null;
    public string $base = '';

    /** @param array<string, string> $env overrides on top of the current environment */
    public function start(array $env): void
    {
        $probe = stream_socket_server('tcp://127.0.0.1:0');
        if ($probe === false) {
            throw new RuntimeException('Cannot allocate a port.');
        }
        $port = (int) substr((string) strrchr((string) stream_socket_get_name($probe, false), ':'), 1);
        fclose($probe);

        $public = BASE_PATH . '/public';
        $log = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nyiltnap-test-server.log';
        $this->process = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $public, $public . '/index.php'],
            [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']],
            $pipes,
            BASE_PATH,
            array_merge(getenv(), $env),
        );
        if (!is_resource($this->process)) {
            throw new RuntimeException('Cannot start php -S.');
        }
        $this->base = 'http://127.0.0.1:' . $port;

        for ($i = 0; $i < 50; $i++) {
            try {
                $socket = fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
                fclose($socket);

                return;
            } catch (ErrorException) {
                usleep(100_000); // not listening yet
            }
        }
        throw new RuntimeException('php -S did not start on port ' . $port);
    }

    public function stop(): void
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process);
            proc_close($this->process);
        }
        $this->process = null;
    }
}

final class HttpResponse
{
    /** @param array<string, string> $headers lower-case names */
    public function __construct(public int $status, public array $headers, public string $body)
    {
    }

    public function header(string $name): string
    {
        return $this->headers[strtolower($name)] ?? '';
    }

    public function csrf(): string
    {
        preg_match('/name="_csrf" value="([a-f0-9]{64})"/', $this->body, $m);

        return $m[1] ?? '';
    }

    public function formToken(): string
    {
        preg_match('/name="_form_token" value="([a-f0-9]{32})"/', $this->body, $m);

        return $m[1] ?? '';
    }
}

/** One "browser": keeps its own cookies across requests. */
final class HttpClient
{
    private CurlHandle $handle;

    public function __construct(private string $base)
    {
        $this->handle = curl_init();
        curl_setopt_array($this->handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEFILE     => '',   // in-memory cookie engine
            CURLOPT_PROXY          => '',
            CURLOPT_NOPROXY        => '*',
            CURLOPT_TIMEOUT        => 30,
        ]);
    }

    public function get(string $path): HttpResponse
    {
        return $this->send('GET', $path);
    }

    /** @param array<string, mixed> $fields */
    public function post(string $path, array $fields = []): HttpResponse
    {
        return $this->send('POST', $path, $fields);
    }

    /** @param array<string, mixed>|null $fields */
    public function send(string $method, string $path, ?array $fields = null): HttpResponse
    {
        $h = $this->handle;
        curl_setopt($h, CURLOPT_URL, $this->base . $path);
        curl_setopt($h, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($h, CURLOPT_HTTPGET, $method === 'GET');
        if ($fields !== null) {
            curl_setopt($h, CURLOPT_POSTFIELDS, http_build_query($fields));
        }
        $raw = curl_exec($h);
        if ($raw === false) {
            throw new RuntimeException('HTTP request failed: ' . curl_error($h));
        }
        $headerSize = (int) curl_getinfo($h, CURLINFO_HEADER_SIZE);
        $status = (int) curl_getinfo($h, CURLINFO_RESPONSE_CODE);

        $headers = [];
        foreach (preg_split('/\r?\n/', substr((string) $raw, 0, $headerSize)) ?: [] as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($name))] = trim($value);
            }
        }

        return new HttpResponse($status, $headers, substr((string) $raw, $headerSize));
    }

    public function sessionId(): string
    {
        foreach (curl_getinfo($this->handle, CURLINFO_COOKIELIST) ?: [] as $line) {
            $parts = explode("\t", (string) $line);
            if (($parts[5] ?? '') === 'nyiltnap_session') {
                return $parts[6] ?? '';
            }
        }

        return '';
    }
}
