# CHANGELOG – TrainerApp

Všechny významné změny v projektu jsou dokumentovány zde.

Formát je založen na [Keep a Changelog](https://keepachangelog.com/cs_CZ/) a projekt se řídí [Sémantické verzování](https://semver.org/cs).

---

## [1.2.0] – 10. května 2026

### ✨ Novinky

#### Katalog sportovních míst (Venues)
- **Nová tabulka `training_venues`** – centrální seznam všech sportovních míst (posilovny, běžné trasy, golfová hřiště)
  - Sloupce: `id`, `name`, `address`, `note`, `is_active`, `created_by_coach_id`, `created_at`, `updated_at`
  - Runtime migrace v `config/database.php` zajišťuje kompatibilitu
  
- **Admin panel** (`admin/venues.php`) – správa sportovních míst
  - Responzivní kartový layout (bez horizontálního scrollování)
  - Vyhledávání a filtrování (Všechna/Aktivní/Neaktivní)
  - Přidání nového místa (název + adresa + poznámka)
  - Editace inline (každé místo je editovatelné na místě)
  - Smazání s **bezpečným nahrazením**: pokud je místo používáno v tréninku, aplikace vyžaduje výběr náhradního místa
  - Progresivní načítání (12 míst najednou, tlačítko "Načíst více")

- **Automatické pamatování míst** – nová místa z formulářů se automaticky přidají do katalogu
  - Nová helper funkce `rememberTrainingVenue($name, $coachId)` v `config/database.php`
  - Normalizace názvů (`normalizeTrainingVenueName()`) – trim, sjednocení mezer, limit na 255 znaků

- **Integrace do formulářů tréninků** – výběr místa v každém tréninku
  - Běžný trénink (`training_session.php`)
  - Párový trénink (`training_paired_session.php`)
  - Běh na páse (`training_run_treadmill_detail.php`)
  - Běh venku (`training_run_outdoor_detail.php`)
  - Formulář: select box s "Název - Adresa" + možnost "Jiné místo (zadat ručně)" s textovým polem
  - Na uložení se automaticky normalizuje a přidá do katalogu, pokud je nové

#### Správa smazaných tréninků pro administrátory
- **Nová stránka** `admin/coach_deleted_trainings.php` – obnova a trvalé smazání tréninků
  
- **Hromadné operace**
  - Multiselect checkboxy pro výběr tréninků
  - Tlačítko "Vybrat vše"
  - "Export zálohy vybraných" – stáhne SQL backup všech vybraných tréninků
  - "Trvale smazat vybrané" – permanentní smazání s explicitním potvrzením
  
- **Export SQL backupu**
  - Zahrnuje všechny související tabulky (training_sessions, session_series, training_session_exercises, run_treadmill_sessions, run_outdoor_sessions, run_outdoor_splits, golf_sessions, golf_holes)
  - Správné pořadí INSERT statements (dependencies-first)
  - UTF-8 kompatibilní
  - Lze později importovat do databáze
  
- **Trvalé smazání**
  - Potvrzovací dialog s výčtem tréninků
  - Transaktivní operace (vše nebo nic)
  - Bezpečné pořadí mazání (run_outdoor_splits → run_outdoor_sessions → golf_holes → golf_sessions → training_session_exercises → session_series → training_sessions)
  - Explicitní error handling a detailné hlášení chyb

- **Jednotlivá obnova**
  - Každý trénink má tlačítko "Obnovit"
  - Obnoví se bez smazání z historie

### 🔧 Technické změny

- **Helper funkce** v `config/database.php`:
  - `normalizeTrainingVenueName($name)` – normalizace názvu místa
  - `getTrainingVenues($includeInactive=false)` – vrátí seznam všech (aktivních) míst
  - `rememberTrainingVenue($name, $coachId)` – automatické přidání nového místa
  - `adminTableExists($tableName)` – bezpečná kontrola tabulky (bugfix pro MySQL bind parameters)
  - `adminFetchBySessionIds()` – hromadný export tréninků s všemi relacemi

- **Database změny**:
  - Runtime migrace pro `training_venues` tabulku v `config/database.php`
  - Soft-delete sloupce v `training_sessions` (`deleted_by_coach_at`, `deleted_by_coach_id`) pro audit trail
  - Správné indexy a foreign key constraints

- **Frontend**:
  - JavaScript pro toggle visibility textového pole na "Jiné místo"
  - Responzivní kartový layout v admin venues
  - Multiselect UI s vizuálním indikátorem počtu vybraných

### 📝 Commit historie

```
be3c646 - Add venue catalog with admin management and form selectors
  - Venues CRUD operations (admin/venues.php)
  - Form integration (training_session.php, training_paired_session.php, etc.)
  - Helper functions for venue management and auto-add
  - Runtime migration for training_venues table
  - SQL schema updates

[plus] - Admin deleted trainings recovery (coach_deleted_trainings.php)
  - Hromadný export SQL backupu
  - Hromadné trvalé smazání s bezpečnostními pojistkami
  - Transactional safety for bulk deletions
  - Proper foreign key deletion order
```

---

## [1.1.01] – duben/květen 2026

### ✨ Novinky

#### Speciální sporty (Early version)
- Systém pro golf, běh venku a běh na páse
- Vlastní datové modely pro každý sport
- Tabulky: `golf_sessions`, `golf_holes`, `run_outdoor_sessions`, `run_outdoor_splits`, `run_treadmill_sessions`

#### Golf detail stránka (`training_golf_detail.php`)
- Responsive layout pro mobilní zařízení
- Čeština (UTF-8) pro všechny texty
- Zobrazení skóre, PAR, HCP

#### Běh na páse (`training_run_treadmill_detail.php`)
- Detail běhu na páse s parametry (čas, km, sklon, tempo)
- Post-run entry form (zadávání dat po tréninku)
- Responsive design

#### Běh venku (`training_run_outdoor_detail.php`)
- Detail běhu venku s splity
- Sledování tempa a RPE
- Mapování trasy (pokud dostupné)

### 🐛 Opravy

- Mobilní optimalizace formulářů (CSS responsive)
- Česká kódování (UTF-8) v e-mailech a databázi
- Těkavá data v session variables

### 📚 Dokumentace

- Vloženo `ARCHITECTURE.md` – přehled technické architektury

---

## [1.0.0] – 2025

### 🎉 První release

- ✅ Správa atletů (přidání, editace, smazání)
- ✅ Správa tréninků (tvorba, plánování, provádění)
- ✅ Párové tréninky
- ✅ Sledování pokroků (grafy)
- ✅ Admin panel (správa trenérů, cvičení)
- ✅ Autentizace (trenéř, admin)
- ✅ Soft-delete (tréninky se nemazou, jen označují)
- ✅ E-mail notifikace
- ✅ CSV export

---

## Legenda

- **✨ Novinky** – nové funkce
- **🔧 Technické změny** – interní refactoring, optimalizace
- **🐛 Opravy** – bugfixy
- **📚 Dokumentace** – nová/aktualizovaná dokumentace
- **⚠️ Breaking Changes** – změny, které mohou zlomit existující kód
- **🔐 Bezpečnost** – bezpečnostní patche

---

**Poslední aktualizace**: 10. května 2026  
**Verze**: 1.2.0
