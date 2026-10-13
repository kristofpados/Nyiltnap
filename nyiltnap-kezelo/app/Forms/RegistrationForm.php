<?php

declare(strict_types=1);

namespace App\Forms;

use App\Core\Validator;
use App\Models\Registration;

/**
 * Server-side validation for the public registration form. This is the
 * authority — browser validation is only a convenience layer on top.
 */
final class RegistrationForm
{
    public const NAME_MIN = 2;
    public const NAME_MAX = 120;
    public const SCHOOL_MIN = 2;
    public const SCHOOL_MAX = 180;

    /** Letters/marks of any script plus space, hyphens, apostrophes and dots. */
    private const NAME_PATTERN = "/^[\\p{L}\\p{M}][\\p{L}\\p{M} .'\\x{2019}\\x{2010}\\-]*$/u";

    /** Longest value echoed back into the form after a failed submission. */
    private const OLD_VALUE_LIMIT = 300;

    /** @var array<string, string> field => Hungarian message, in form order */
    private array $errors = [];

    /** @var array{full_name: string, current_school: string, programs: list<int>, residency: ?string, graduating: ?string} */
    private array $old = ['full_name' => '', 'current_school' => '', 'programs' => [], 'residency' => null, 'graduating' => null];

    /** @var array{full_name: string, current_school: string, residency: string, is_graduating: bool, program_ids: list<int>}|null */
    private ?array $clean = null;

    /** @param array<int, array<string, mixed>> $activePrograms keyed by program ID */
    public function __construct(private readonly array $activePrograms)
    {
    }

    /** @param array<string, mixed> $input usually $_POST */
    public function validate(array $input): bool
    {
        $this->errors = [];
        $this->clean = null;

        $name = $this->validateName($input['full_name'] ?? '');
        $school = $this->validateSchool($input['current_school'] ?? '');
        $programIds = $this->validatePrograms($input['programs'] ?? []);
        $residency = $this->validateResidency($input['residency'] ?? null);
        $graduating = $this->validateGraduating($input['graduating'] ?? null);

        if ($this->errors !== []) {
            return false;
        }

        $this->clean = [
            'full_name'      => (string) $name,
            'current_school' => (string) $school,
            'residency'      => (string) $residency,
            'is_graduating'  => (bool) $graduating,
            'program_ids'    => $programIds,
        ];

        return true;
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** Non-sensitive values to re-populate the form after a failed submission. */
    public function old(): array
    {
        return $this->old;
    }

    /** @return array{full_name: string, current_school: string, residency: string, is_graduating: bool, program_ids: list<int>} */
    public function data(): array
    {
        if ($this->clean === null) {
            throw new \LogicException('Call validate() successfully before reading data().');
        }

        return $this->clean;
    }

    private function validateName(mixed $raw): ?string
    {
        $name = Validator::singleLine($raw);
        if ($name === null) {
            $this->errors['full_name'] = 'A megadott név érvénytelen karaktereket tartalmaz.';

            return null;
        }
        $this->old['full_name'] = mb_substr($name, 0, self::OLD_VALUE_LIMIT);

        $length = Validator::length($name);
        $error = match (true) {
            $name === ''                                     => 'Add meg a teljes nevedet.',
            $length < self::NAME_MIN                         => 'A név legalább ' . self::NAME_MIN . ' karakter legyen.',
            $length > self::NAME_MAX                         => 'A név legfeljebb ' . self::NAME_MAX . ' karakter lehet.',
            Validator::hasInvisibleCharacters($name)         => 'A név nem megengedett (láthatatlan) karaktert tartalmaz.',
            preg_match(self::NAME_PATTERN, $name) !== 1      => 'A név csak betűket, szóközt, kötőjelet, pontot és aposztrófot tartalmazhat.',
            Validator::countLetters($name) < 2               => 'A név legalább két betűt tartalmazzon.',
            default                                          => null,
        };

        if ($error !== null) {
            $this->errors['full_name'] = $error;

            return null;
        }

        return $name;
    }

    private function validateSchool(mixed $raw): ?string
    {
        $school = Validator::singleLine($raw);
        if ($school === null) {
            $this->errors['current_school'] = 'Az iskola neve érvénytelen karaktereket tartalmaz.';

            return null;
        }
        $this->old['current_school'] = mb_substr($school, 0, self::OLD_VALUE_LIMIT);

        $length = Validator::length($school);
        $error = match (true) {
            $school === ''                               => 'Add meg, melyik iskolába jársz jelenleg.',
            $length < self::SCHOOL_MIN                   => 'Az iskola neve legalább ' . self::SCHOOL_MIN . ' karakter legyen.',
            $length > self::SCHOOL_MAX                   => 'Az iskola neve legfeljebb ' . self::SCHOOL_MAX . ' karakter lehet.',
            Validator::hasInvisibleCharacters($school)   => 'Az iskola neve nem megengedett (láthatatlan) karaktert tartalmaz.',
            Validator::countLetters($school) < 1         => 'Az iskola neve betűket is tartalmazzon.',
            default                                      => null,
        };

        if ($error !== null) {
            $this->errors['current_school'] = $error;

            return null;
        }

        return $school;
    }

    /**
     * Only IDs of currently active programs are accepted. Posted labels are
     * never trusted; duplicates collapse silently; anything forged, inactive
     * or unknown rejects the whole selection so the applicant can re-check it.
     *
     * @return list<int>
     */
    private function validatePrograms(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            $raw = [];
        }

        $invalid = !is_array($raw) || count($raw) > 50;
        $ids = [];
        if (is_array($raw) && !$invalid) {
            foreach ($raw as $value) {
                if (!Validator::isIdString($value) || !isset($this->activePrograms[(int) $value])) {
                    $invalid = true;
                    continue;
                }
                $ids[(int) $value] = (int) $value;
            }
        }

        // Keep the valid ticks checked when the form is shown again, in display order.
        $this->old['programs'] = array_values(array_filter(array_keys($this->activePrograms), static fn (int $id) => isset($ids[$id])));

        $error = match (true) {
            $this->activePrograms === [] => 'Jelenleg nincs választható szakma. Kérjük, jelezd az iskolának.',
            $invalid                     => 'A kiválasztott szakmák között érvénytelen elem szerepel. Kérjük, jelöld meg újra a listából.',
            $ids === []                  => 'Válassz legalább egy szakmát, amely érdekel.',
            default                      => null,
        };
        if ($error !== null) {
            $this->errors['programs'] = $error;

            return [];
        }

        return array_values($ids);
    }

    private function validateResidency(mixed $raw): ?string
    {
        if (is_string($raw) && in_array($raw, Registration::RESIDENCIES, true)) {
            $this->old['residency'] = $raw;

            return $raw;
        }

        $this->errors['residency'] = ($raw === null || $raw === '')
            ? 'Válaszd ki, hogy kollégista vagy bejárós lennél.'
            : 'Érvénytelen választás. Kérjük, jelöld meg a két lehetőség egyikét.';

        return null;
    }

    private function validateGraduating(mixed $raw): ?bool
    {
        if ($raw === '1' || $raw === '0') {
            $this->old['graduating'] = $raw;

            return $raw === '1';
        }

        $this->errors['graduating'] = ($raw === null || $raw === '')
            ? 'Jelöld meg, hogy végzős vagy-e.'
            : 'Érvénytelen választás. Kérjük, jelöld meg az „Igen” vagy a „Nem” lehetőséget.';

        return null;
    }
}
