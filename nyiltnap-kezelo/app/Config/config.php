<?php

declare(strict_types=1);

use App\Core\Env;

/*
 * Central configuration.
 *
 * Secrets (database credentials, environment) come from .env or real
 * environment variables — never hard-code them here.
 *
 * School identity and event details live in the 'brand' section below:
 * this is the ONE place to change the school name, the event date and the
 * privacy text. Brand colours are in public/assets/css/tokens.css.
 */

return [
    'app' => [
        'env'         => Env::get('APP_ENV', 'production'),
        'debug'       => Env::bool('APP_DEBUG', false),
        'timezone'    => Env::get('APP_TIMEZONE', 'Europe/Budapest'),
        'trust_proxy' => Env::bool('TRUST_PROXY', false),
    ],

    'db' => [
        'host'   => Env::get('DB_HOST', '127.0.0.1'),
        'port'   => (int) Env::get('DB_PORT', '3306'),
        'name'   => Env::get('DB_NAME', 'nyiltnap_kezelo'),
        'user'   => Env::get('DB_USER', ''),
        'pass'   => Env::get('DB_PASS', ''),
        'socket' => Env::get('DB_SOCKET', ''),
    ],

    'session' => [
        'name'         => 'nyiltnap_session',
        'idle_timeout' => 2 * 60 * 60,           // seconds of inactivity before an admin session expires
        'secure'       => Env::get('SESSION_SECURE', 'auto'), // auto | true | false
    ],

    'security' => [
        // Login throttling: at most this many failures per IP within the window.
        'login_max_attempts'   => 5,
        'login_window_minutes' => 15,
    ],

    'admin' => [
        'per_page_options' => [25, 50, 100],
        'default_per_page' => 25,
        'recent_count'     => 8,
    ],

    // -----------------------------------------------------------------
    // ISKOLAI ARCULAT — cseréld le a valós adatokra!
    // -----------------------------------------------------------------
    'brand' => [
        'school_name' => 'NTSZKI',
        'event_name'  => 'Nyílt nap',
        'event_year'  => '2026',

        // PÉLDA értékek. Ha egy mezőt üresre állítasz, az elem nem jelenik meg.
        'event_date'  => '2026. november 14., szombat',
        'event_time'  => '9:00–13:00',
        'event_place' => 'Az iskola főépülete',

        // Adatkezelési tájékoztató SABLON — az iskola adatvédelmi felelősének
        // jóvá kell hagynia, és a megőrzési időt az iskolának kell meghatároznia.
        'privacy_paragraphs' => [
            'A regisztráció során megadott adatokat (teljes név, jelenlegi iskola, érdeklődési terület, kollégiumi igény, végzős státusz) kizárólag a nyílt nap megszervezéséhez és a látogatók fogadásának előkészítéséhez használjuk fel.',
            'Az adatokhoz csak az iskola erre jogosult munkatársai férnek hozzá. Az adatokat harmadik félnek nem adjuk át, és a nyílt nap után, az iskola által meghatározott határidőn belül töröljük.',
            'Kérdés esetén vagy ha a jelentkezésed törlését kéred, fordulj az iskola titkárságához.',
        ],
        'privacy_template_note' => 'Ez a tájékoztató sablon; a végleges szöveget az iskola hagyja jóvá.',
    ],
];
