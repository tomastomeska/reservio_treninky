# TrainerApp v1.1.01 – Architektura speciálních sportů

## Přehled

Dosud aplikace podporovala pouze standardní cviky s pevnou strukturou: váha, opakování, dopomoc.

Verze 1.1.01 zavádí tři nové speciální sporty s vlastní datovými strukturami:

1. **Golf** – jamky, PAR, HCP, hřiště, počasí
2. **Běh venku** – tempo, splity, RPE, povrch, metriky běhu
3. **Běh na páse** – čas, km, kalorií, lokace

## Datový model

### Stávající struktura
- `exercises` – názvy cvičení (všechny typu)
- `workout_sets` – sady tréninků
- `workout_set_exercises` – cvičení v sadě
- `training_sessions` – konkrétní tréninky
- `session_series` – data z cvičení (váha, reps, dopomoc)

### Nová struktura

#### 1. Rozšíření existujících tabulek

**exercises**
- Přidat sloupec `sport_type` (enum: 'standard', 'golf', 'run_outdoor', 'run_treadmill')
- Pokud je speciální typ, není potřeba zadávat váhu/opakování

**workout_sets**
- Přidat sloupec `sport_type` (stejné enum)
- Indikuje, jaké cviky se v sadě mohou nacházet

**training_sessions**
- Přidat sloupec `sport_type` (odvozeno z workout_set)
- Zůstávají stávající sloupce pro kompatibilitu

#### 2. Nové tabulky

**golf_sessions**
```sql
- session_id (FK -> training_sessions)
- started_at, ended_at
- duration_minutes
- course_name
- num_holes (9, 18, custom)
- game_type (training, tournament, friendly)
- distance_km
- calories_burned
- weather
- players (JSON nebo delimited string)
- handicap_after (nullable)
- feeling (text)
```

**golf_holes**
```sql
- id, golf_session_id
- hole_number
- par
- score (NULL do skončení jamky)
- notes
```

**run_outdoor_sessions**
```sql
- session_id (FK -> training_sessions)
- duration_seconds
- distance_km
- run_type (free, intervals, tempo, race, recovery)
- surface (asphalt, trail, mixed)
- avg_pace
- calories_burned
- max_speed
- step_count
- rpe (1-10)
- feeling (text)
- tempo_variability
```

**run_outdoor_splits**
```sql
- id, run_session_id
- km_marker
- split_time
- pace
- max_speed_at_km
```

**run_treadmill_sessions**
```sql
- session_id (FK -> training_sessions)
- duration_seconds
- distance_km
- calories_burned
- location (text)
- feeling (text)
```

## Workflow

### Golf
1. Trenér vytvoří golfsport do sady → seznam jamek se zapíše při startu sady
2. Klikne "Start Golf" → system zaznamenà `started_at`
3. Pro každou jamku zadá: PAR (preload z předchozí hry na hřišti), vlastní skóre
4. Na konci: klik "Konec golfu" → `ended_at` se zaznamená
5. Extra data (počasí, pocit, HCP) lze doeditovat v detailu

### Běh venku
1. Trenér vytvoří běh do sady → bez specifických dat
2. Klikne "Start běhu" → `started_at`
3. Klikne "Konec běhu" → `ended_at`
4. Doedituje: vzdálenost, typ běhu, povrch, RPE, pocit
5. Splity lze zadat/doeditovat v detailu

### Běh na páse
1. Trenér vytvoří běh do sady
2. Klikne "Start běhu" → `started_at`
3. Klikne "Konec běhu" → `ended_at`
4. Doedituje: čas, km, kalorií, lokaci, pocit
5. Jednoduchý workflow, nejméně dat

## UI/UX změny

1. Při vytváření cviku v sadě → volba typu (standardní vs. golf vs. běh)
2. Při startu tréninku → jiný formulář podle typu
3. Při práci s treninkem → speciální views pro každý typ
4. Statistiky → speciální dashboard pro každý typ

## Bezpečnost dat

- **Lokální testování**: Jakákoliv nová data se vytváří pouze v lokální DB
- **Produkce**: SQL migrace se nasadí jen pro schéma (DDL), nikdy DML
- **Rollback**: Pokud se něco pokazí, lze vrátit na poslední commit před migrací
