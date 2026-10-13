-- =====================================================================
-- Nyílt nap kezelő — alapadatok (CSAK szakmák)
--
-- Ezek SEMLEGES PÉLDA szakmák. Cseréld le őket az iskola valódi
-- képzéseire (lásd README → „Szakmák beállítása”).
--
-- Idempotens: a már létező (azonos slug-ú) sorokat nem írja felül,
-- így az adminisztrátor módosításai megmaradnak.
-- Admin felhasználót és jelentkezőt ez a fájl szándékosan NEM hoz létre.
--
-- icon: a public/assets ikonkészlet egyik neve
--       (code, gear, chip, robot, pen, bolt, layers, school, users)
-- =====================================================================

SET NAMES utf8mb4;

INSERT INTO programs (slug, name, description, icon, sort_order, is_active) VALUES
    ('informatika',       'Informatika és szoftverfejlesztés', 'Programozás, webfejlesztés, hálózatok',        'code',  10, 1),
    ('gepeszet',          'Gépészet',                           'Gépek tervezése, gyártástechnológia, CNC',    'gear',  20, 1),
    ('elektronika',       'Elektronika és elektrotechnika',     'Áramkörök, mérés, automatizálás',             'chip',  30, 1),
    ('mechatronika',      'Mechatronika és robotika',           'Robotok, vezérlések, okos rendszerek',        'robot', 40, 1),
    ('kreativ-digitalis', 'Kreatív digitális tervezés',         'Grafika, mozgókép, felülettervezés',          'pen',   50, 1),
    ('energetika',        'Épületgépészet és energetika',       'Megújuló energia, fűtés- és klímatechnika',   'bolt',  60, 1)
ON DUPLICATE KEY UPDATE id = id;
