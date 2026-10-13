# Tesztelés

## Automatikus tesztek

```bash
php tests/run.php              # teljes futás
php tests/run.php --skip-lint  # php -l nélkül (gyorsabb)
```

- Függőség nélküli tesztkeret (`tests/harness.php`): assertionök, eldobható adatbázis, valódi `php -S` szerver, sütiket kezelő HTTP-kliens.
- Az adatbázis neve a `TEST_DB_NAME`, alapértelmezés szerint `<DB_NAME>_test`. A futtató **megtagadja a futást**, ha a név nem `_test` végű, vagy megegyezik a `DB_NAME`-mel. A tesztadatbázis tábláit minden futás eldobja és újraépíti a `database/schema.sql` és a `database/seed.sql` alapján.
- Kilépési kód: `0`, ha minden sikeres; `1`, ha bármi elbukott. CI-ban is használható.

| Csoport | Mit ellenőriz |
|---|---|
| PHP szintaxis | `php -l` minden `.php` fájlon |
| Séma | Import sikeres és idempotens, InnoDB + `utf8mb4_unicode_ci`, a név nem egyedi |
| Űrlap | Normalizálás, nemzetközi nevek, 22 érvénytelen bemenet, inaktív és hiányzó szakmák |
| Mentés és statisztika | Több szakma, azonos nevek, tranzakció-visszagörgetés, pontos összesítők ismert adathalmazon, napi bontás, üres adatbázis |
| Szűrés és törlés | Lapozás és túlcímzés, kombinált szűrők, `%` / `_` / ékezet / aposztróf keresés, SQL injection, fehérlistás rendezés, biztonságos törlés |
| Biztonsági segédfüggvények | Escapelés, open redirect, brute-force korlátozás, jelszó-hash |
| HTTP (végponttól végpontig) | CSRF, PRG, dupla beküldés, XSS, admin védelem, belépés és kilépés, munkamenet-regenerálás, CSV, törlési folyamat, 404/405/429/503 |

## Kézi ellenőrzőlista

A fejlesztés során headless Chrome-mal **1440, 1280, 1024, 768 és 390 px** szélességen végignéztük a nyilvános űrlapot (üres, hibás és sikeres állapot), a bejelentkezést, az áttekintést, a listát (szűrővel és törlési párbeszédablakkal), a statisztikákat és a 404-es oldalt, és a böngészőkonzolban nem volt hiba. Új változtatás után érdemes az alábbiakat kézzel is megnézni:

**Billentyűzet és akadálymentesség**
- [ ] `Tab` az oldal elején: megjelenik az „Ugrás a tartalomra” link.
- [ ] Minden mező, szakma-csempe és rádiógomb elérhető `Tab`-bal; a csempék `Szóköz`-zel jelölhetők; a fókuszkeret jól látható.
- [ ] Hibás beküldés után a fókusz a hibaösszesítőre kerül, és a linkjei a megfelelő mezőre ugranak.
- [ ] Admin törlés: a párbeszédablakban az alapértelmezett fókusz a „Mégsem”. Az `Esc` bezárja, és a fókusz visszatér a „Törlés” gombra.
- [ ] Állapotok szöveggel is jelölve (Kollégista/Bejárós, Igen/Nem), nem csak színnel.
- [ ] Rendszerszintű „csökkentett mozgás” mellett nincs animáció.

**JavaScript nélkül** (böngészőben letiltva)
- [ ] Az űrlap beküldhető; a szerver magyar hibaüzenetekkel válaszol, a helyes mezők megmaradnak.
- [ ] Az admin „Törlés” egy megerősítő oldalra visz; a végleges törlés onnan működik.
- [ ] A szűrők és a lapozás működnek; a „Nyomtatás” gomb rejtve van, mert JS nélkül nem működne.

**Képernyőméretek**
- [ ] 1440 / 1024 / 768 / 390 px: nincs vízszintes görgetés, nincs átfedő szöveg.
- [ ] Táblagépen (768–1024 px) az admin táblázat kártyanézetre vált, a törlés gomb a kártya jobb felső sarkában van.

**Biztonság**
- [ ] `/.env`, `/app/bootstrap.php`, `/database/schema.sql` → 403 vagy 404 (Apache alatt is).
- [ ] Kijelentkezés után a böngésző „Vissza” gombja nem mutat admin adatot (frissítéskor a bejelentkezésre irányít).
- [ ] `APP_DEBUG=false` mellett a hibaoldal nem mutat technikai részletet.
