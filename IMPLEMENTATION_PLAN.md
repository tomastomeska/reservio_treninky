# Implementační plán – speciální sporty v1.1.01

## Fáze 1: Backend funkce (config/database.php, includes/functions.php)

### Funkce pro správu golfu

```php
// Vytvoření golf session po startu tréninku
function createGolfSession(int $sessionId, string $courseName, int $numHoles = 18): int

// Přidání jamky
function addGolfHole(int $golfSessionId, int $holeNumber, int $par): int

// Update skóre jamky
function updateGolfHoleScore(int $holeId, int $score): bool

// Načtení předchozích par hodnot (na stejném hřišti)
function getGolfCourseHistory(int $coachId, string $courseName): array

// Výpočet statistik golfu (celkem parů, +/-, HCP)
function calculateGolfStats(int $golfSessionId): array
```

### Funkce pro běh venku

```php
// Vytvoření běžecké session
function createRunOutdoorSession(int $sessionId, int $durationSeconds, float $distanceKm): int

// Přidání splitu
function addRunOutdoorSplit(int $runSessionId, float $kmMarker, string $splitTime, string $pace): int

// Výpočet tempa (minutes:seconds per km)
function calculatePace(float $distanceKm, int $secondsElapsed): string

// Výpočet variability tempa
function calculateTempoVariability(int $runSessionId): float

// Historie běhů (průměr, trend)
function getRunOutdoorHistory(int $athleteId, int $limit = 10): array
```

### Funkce pro běh na páse

```php
// Vytvoření treadmill session
function createRunTreadmillSession(int $sessionId, int $durationSeconds, float $distanceKm): int

// Průměr za poslední běhy
function getTreadmillStats(int $athleteId, int $limit = 5): array
```

## Fáze 2: PHP soubory pro UI

### Stávající soubory na úpravy

1. **exercises.php, exercises_add.php**
   - Přidat výběr sport_type (standard/golf/run_outdoor/run_treadmill)
   - Pro speciální sporty skrýt pole weight/reps/assistance

2. **sady.php, sada_add.php, sada_edit.php**
   - Přidat výběr sport_type
   - Při přidávání cviku – filtrovat jen relevantní typ

3. **training_start.php, training_session.php**
   - Detekovat sport_type z workout_setu
   - Zobrazit správný formulář (standardní vs. golf vs. běh)

4. **training_detail.php**
   - Speciální view pro golf (jamky, par, score)
   - Speciální view pro běh (splity, tempo, RPE)
   - Speciální view pro treadmill (jednoduché)

### Nové soubory

1. **training_golf_start.php** – zahájení golfu (formulář pro hřiště, počet jamek)
2. **training_golf_hole.php** – zadání/editace jamky
3. **training_golf_detail.php** – detail golfu s historií hřiště
4. **training_run_outdoor_start.php** – zahájení běhu venku
5. **training_run_outdoor_splits.php** – správa splitů
6. **training_run_treadmill_start.php** – zahájení treadmillu

## Fáze 3: API endpointy

Přidat do [api/](../api/)

```
api/save_golf_hole.php         – POST s hole_number, par, score
api/save_run_split.php         – POST s km_marker, split_time, pace
api/update_golf_session.php    – PUT pro weather, feeling, handicap_after
api/get_course_history.php     – GET historii hřiště (páry)
```

## Fáze 4: Ověření a statistiky

### Dashboard úpravy
- Pro golf: počet odehraných jam, průměrný score, HCP trend
- Pro běh: km za měsíc, průměrné tempo, trend výkonu
- Pro treadmill: km/měsíc, trend

### Tabulka s historií
- Golf: hřiště, datum, jamky, skóre, počasí
- Běh venku: datum, km, tempo, RPE, feeling
- Treadmill: datum, čas, km, kalorií

## Fáze 5: Workflow v aplikaci

### Příklad – Golf

1. **Příprava**
   - Trenér/sportovec si v [sady.php](../sady.php) vytvoří novou sadu
   - Vybere typ: Golf
   - Přidá cviko: "Golf" (výběr ze seznamu)

2. **Start tréninku**
   - Klikne na sadu → [training_start.php](../training_start.php)
   - System detekuje: sport_type = 'golf'
   - Formulář: "Kde jsi hrál?", "Kolik jamek?" (9/18/custom)
   - Klikne "Zahájit golf" → `golf_sessions` záznam + `training_sessions` záznam

3. **Během hry**
   - URL: [training_session.php?id=X](../training_session.php?id=X)
   - Zobrazí: seznam jamek (1–18)
   - Pro každou jamku: PAR (preload z historie hřiště), vlastní skóre
   - Sekvenční editace jamek, nebo všechny najednou

4. **Konec hry**
   - Klikne "Konec golf hry"
   - System zaznamenà `ended_at`
   - Přesměr na [training_golf_detail.php](training_golf_detail.php)

5. **Post-game doeditace**
   - Možnost přidat: počasí, pocit, HCP, hráče
   - Možnost vrátit se a opravit jamky
   - Na konci "Ulož a zavři" → sesssion se označí `completed_at`

## Migrační krok – lokální implementace

1. Nejdřív schéma (sql/2026-05-09_special_sports_v1.sql) ✅
2. Pak PHP funkce v [config/database.php](../config/database.php)
3. Pak jednotlivé PHP stránky (postupně)
4. Testování lokálně
5. Commit a zhotovení PRu do main
6. Deploy na server (jen DDL!)
7. Testování na produkci

## Poznámky k bezpečnosti

- **Nikdy** se nepřenášejí testovací data na server
- SQL migrace na produkci: jen `ALTER TABLE` a `CREATE TABLE`, bez `INSERT`
- Produkční data zůstanou nedotčená
- Po aplikaci migrací musí aplikace na serveru opět běžet bez chyb
