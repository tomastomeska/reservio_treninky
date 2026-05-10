# TrainerApp – Systém pro správu tréninků a atletů

TrainerApp je webová aplikace pro trénery k organizaci tréninků, správě atletů, sledování pokroků a správě speciálních sportů (golf, běh venku, běh na páse). Aplikace je postavena na PHP 8+, MySQL a Bootstrap 5.3.

## 📋 Obsah

- [Přehled](#přehled)
- [Požadavky](#požadavky)
- [Instalace](#instalace)
- [Konfigurace](#konfigurace)
- [Struktura aplikace](#struktura-aplikace)
- [Klíčové moduly](#klíčové-moduly)
- [Admin panel](#admin-panel)
- [Datový model](#datový-model)

---

## 🎯 Přehled

TrainerApp umožňuje:

- **Správu atletů** – přidávání, editace, smazání atletů s jejich profilem
- **Správu tréninků** – tvorba tréninků, přiřazení atletům, plánování sezóny
- **Sledování pokroků** – grafické zobrazení pokroků, export do CSV
- **Speciální sporty** – golf, běh venku, běh na páse s vlastní strukturou dat
- **Správu tréninků v párech** – párové tréninky pro více atletů
- **Obnovu smazaných tréninků** – obnovení nebo trvalé smazání s exportem
- **Správu trenérů** – admin panel pro spravování trenérů a jejich práv

---

## ⚙️ Požadavky

- **PHP 8.0+** s moduly: `pdo`, `pdo_mysql`, `json`, `curl`, `fileinfo`
- **MySQL 8.0+** nebo kompatibilní (MariaDB)
- **WAMP/LAMP/LEMP** nebo podobné prostředí
- **Webový server** s URL rewrites (Apache s mod_rewrite, Nginx, apod.)
- **Přístup k SMTP serveru** (volitelné, pro e-mailové notifikace)

---

## 🚀 Instalace

### 1. Příprava databáze

```bash
# V MySQL/phpMyAdmin vytvořte databázi
CREATE DATABASE marcelmiler CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# Importujte schéma
mysql -u uzivatel -p marcelmiler < sql/schema.sql
```

### 2. Konfigurace prostředí

```bash
# Zkopírujte šablonu
cp config/env.example.php config/env.local.php

# Upravte dle vašeho prostředí (host, databáze, heslo, SMTP)
nano config/env.local.php
```

### 3. Inicializace aplikace

1. Otevřete v prohlížeči: `http://localhost/marcelmiler/setup_admin.php`
2. Vytvořte prvního správce (trenéra)
3. Přihlašovací údaje budou poslány na e-mail

### 4. Přihlášení

- **Trenéři**: `http://localhost/marcelmiler/login.php`
- **Administrátoři**: `http://localhost/marcelmiler/login_admin.php`

---

## 🔧 Konfigurace

### config/env.php (dynamická konfigurrace)

```php
// Databáze
define('DB_HOST',    'localhost');
define('DB_NAME',    'marcelmiler');
define('DB_USER',    'root');
define('DB_PASS',    '');

// Base URL (prázdné = auto-detekce)
// define('BASE_URL', '/marcelmiler');

// Bezpečnost (localhost=false, produkce=true)
define('SESSION_SECURE', false);

// Setup admin přístup (lokálně true, produkce false)
define('ENABLE_SETUP_ADMIN', false);

// SMTP notifikace
define('SMTP_HOST',      'smtp.gmail.com');
define('SMTP_PORT',      587);
define('SMTP_USER',      'your-email@gmail.com');
define('SMTP_PASS',      'app-password');
define('SMTP_FROM',      'noreply@app.com');
define('SMTP_FROM_NAME', 'TrainerApp');
```

### Měnění prostředí

Editujte `config/env.local.php` nebo `config/env.production.php` podle potřeby.

---

## 📁 Struktura aplikace

```
marcelmiler/
├── index.php                    # Dashboard trenéra
├── login.php, logout.php        # Přihlášení trenéra
├── login_admin.php              # Přihlášení administrátora
│
├── admin/                       # Administrační panel
│   ├── dashboard.php            # Přehled systému
│   ├── coaches.php              # Správa trenérů
│   ├── venues.php               # Správa sportovišť
│   ├── training_add.php         # Přidání tréninku (admin)
│   ├── coach_deleted_trainings.php # Obnova/trvalé smazání
│   └── ...
│
├── api/                         # API endpointy
│   ├── save_series.php          # Uložení cvičení v tréninku
│   ├── delete_series.php        # Smazání cvičení
│   ├── save_run_treadmill_draft.php  # Běh na páse
│   ├── save_run_outdoor_draft.php    # Běh venku
│   └── ...
│
├── athlete_*.php                # Správa atletů
│   ├── athlete_add.php
│   ├── athlete_detail.php
│   ├── athlete_edit.php
│   └── athlete_delete.php
│
├── training_*.php               # Tréninky a speciální sporty
│   ├── training_new.php             # Nový trénink
│   ├── training_session.php         # Běžný trénink (cvičení)
│   ├── training_paired_session.php  # Párový trénink
│   ├── training_start.php           # Start tréninku
│   ├── training_detail.php          # Detail tréninku
│   ├── training_delete.php          # Smazání tréninku
│   ├── training_golf_start.php      # Golf - start
│   ├── training_golf_detail.php     # Golf - detail
│   ├── training_run_treadmill_start.php      # Běh na páse - start
│   ├── training_run_treadmill_detail.php     # Běh na páse - detail
│   ├── training_run_outdoor_start.php        # Běh venku - start
│   └── training_run_outdoor_detail.php       # Běh venku - detail
│
├── config/                      # Konfigurace
│   ├── env.example.php          # Šablona prostředí
│   ├── env.php                  # Aktivní konfigurační profil
│   ├── database.php             # Inicializace DB + helpers
│   └── config.php               # Ostatní konstanty
│
├── includes/                    # Funkce a autentizace
│   ├── auth.php                 # Trenér autentizace
│   ├── admin_auth.php           # Admin autentizace
│   ├── functions.php            # Pomocné funkce
│   └── header.php               # HTML header šablona
│
├── sql/                         # SQL skripty
│   ├── schema.sql               # Schéma databáze
│   └── *_backups.sql            # Automatické exporty
│
├── assets/
│   ├── css/style.css            # Vlastní styly (Bootstrap 5.3)
│   ├── js/app.js                # Aplikační skripty
│   └── ...
│
└── vendor/                      # Composer dependencies (PHPMailer)
```

---

## 🎮 Klíčové moduly

### 1. **Správa atletů** (`athlete_*.php`)

- **Přidání nového atleta**: `athlete_add.php` → formulář → v databázi
- **Detail atleta**: `athlete_detail.php` → zobrazení profilu, ročníka, skupiny
- **Editace atleta**: `athlete_edit.php` → úprava dat
- **Smazání atleta**: `athlete_delete.php` → soft-delete (označení jako smazaného)
- Atleté jsou přiřazeni **trenérům** (coachi) a mají skupiny (sady cvičení)

### 2. **Tréninky** (`training_*.php`)

#### Běžný trénink (cvičení se vahami/opakováním)
- **Vytvoření**: `training_new.php` → výběr atleta, cvičení
- **Provádění**: `training_session.php` → zadávání vah/opakování/dopomoci
- **Dokončení**: `training_complete.php` → shrnutí, uložení
- **Detail**: `training_detail.php` → zobrazení výsledků

#### Párový trénink (více atletů)
- **Start**: `training_paired_start.php` → výběr atletů
- **Provádění**: `training_paired_session.php` → sdílený trénink
- **Dokončení**: `training_paired_complete.php` → uložení pro každého

#### Speciální sporty

**Golf** (`training_golf_*.php`)
- Start tréninku
- Zadávání jamek, PAR, HCP, hřiště, počasí
- Detail s historií her
- Automatické výpočty skóre

**Běh venku** (`training_run_outdoor_*.php`)
- Start tréninku
- Automatický tracking splitu (každý km)
- RPE (vnímání zátěže), povrch, tempe
- Metriky: čas, km, kalorií, průměrné tempo

**Běh na páse** (`training_run_treadmill_*.php`)
- Start tréninku
- Zadávání parametrů: čas, km, kalorií, sklon, tempo
- Detail s grafem
- Lokace (místnost, posilovny)

### 3. **Sportovní místa** (`admin/venues.php`)

Katalog všech sportovních míst (posilovny, běžné trasy, golfová hřiště).

- **Přidání místa**: Název + Adresa + Poznámka
- **Editace**: Změní se u všech existujících tréninků
- **Filtrování**: Aktivní/neaktivní místa
- **Smazání s nahrazením**: Pokud je místo smazáno, můžete všechny tréninky přepsat na jiné místo
- Aplikace si **automaticky pamatuje** nová místa z formulářů

### 4. **Obnova a trvalé smazání** (`admin/coach_deleted_trainings.php`)

Pro administrátory k obnově nebo trvalému smazání tréninků.

- **Hromadný export**: Vyberte tréninky → stáhne SQL backup
- **Trvalé smazání**: Hromadné smazání s potvrzením (nelze vrátit)
- **Jednotlivá obnova**: Obnovit starý trénink

### 5. **Pokroky a grafy** (`graphs.php`)

- Grafické zobrazení pokroků atletů
- Výběr období, cvičení
- Sledování trendů (váha, opakování, tempo, apod.)
- Export do CSV

### 6. **Profil a změní hesla** (`profile.php`, `change_password.php`)

- Editace vlastního profilu (trenéra)
- Změna hesla
- E-mailová notifikace

---

## 👨‍💼 Admin panel

### Přístup

`http://localhost/marcelmiler/login_admin.php` → admin dash → `admin/`

### Možnosti administrátora

1. **Správa trenérů** (`admin/coaches.php`)
   - Přidání nového trenéra
   - Editace dat (jméno, e-mail, telefo)
   - Smazání trenéra
   - Hromadný export trenérů a jejich tréninků

2. **Správa sportovních míst** (`admin/venues.php`)
   - Katalog míst (posilovny, běžné trasy, golfová hřiště)
   - Hledání a filtrování
   - Přidání/editace/smazání
   - Nahrazení při smazání (všechny tréninky se přepíšou)

3. **Správa smazaných tréninků** (`admin/coach_deleted_trainings.php`)
   - Prohlížení soft-deletovaných tréninků
   - Hromadný export SQL backupu
   - Trvalé smazání s bezpečnostními pojistkami
   - Jednotlivá obnova

4. **Správa cvičení** (`admin/exercises.php`)
   - Přidání nových cvičení
   - Definice typu (standardní, golf, běh, apod.)

5. **Ostatní** (`admin/settings.php`, `admin/email_notifications.php`)
   - Nastavení aplikace
   - E-mailové notifikace trenérům

---

## 📊 Datový model

### Hlavní tabulky

**Uživatelé a správa**
- `coaches` – trenéři a administrátoři
- `athletes` – sportovci
- `athlete_coach` – přiřazení atletů trenérům

**Tréninky a cvičení**
- `exercises` – seznam cvičení (s typem: standard, golf, run_outdoor, run_treadmill)
- `workout_sets` – sady cvičení (tradiční tréninkové plány)
- `training_sessions` – konkrétní tréninky (loga, čas, poznámky)
- `session_series` – data cvičení v tréninku (váha, opakování, dopomoc)

**Speciální sporty**
- `golf_sessions` – data z golfu (jamky, skóre, počasí)
- `golf_holes` – jednotlivé jamky
- `run_outdoor_sessions` – běh venku (tempo, RPE, povrch)
- `run_outdoor_splits` – splity (data za každý km)
- `run_treadmill_sessions` – běh na páse (čas, km, sklon)

**Sportovní místa**
- `training_venues` – katalog sportovních míst (posilovny, běžné trasy)

**Ostatní**
- `login_messages` – zprávy pro trenéry při přihlášení
- `cron_jobs` – plánované úlohy (např. narozeninové maily)

---

## 📱 Responsive design

Aplikace je plně responzivní a funguje na:

- **Desktopu** (1920px+)
- **Tabletu** (768px – 1024px)
- **Mobilním telefonu** (320px – 480px)

Tvorba tréninků je optimalizovaná pro malé obrazovky (hešky, příjemné tlačítko, minimální scrolling).

---

## 🔐 Bezpečnost

- **Autentizace**: Session-based s cookies
- **Autorizace**: Trenéři vidí jen svoje atlety, admini vidí všechno
- **Hesla**: Hashovaná s `password_hash()` a `password_verify()`
- **SQL injection**: Používáme PDO prepared statements
- **CSRF ochrana**: Generování a ověřování CSRF tokenů
- **Soft-delete**: Smazané tréninky se jen označí, nejsou úplně smazány

---

## 🐛 Troubleshooting

### Problém: „Database connection failed"

Zkontrolujte:
1. Je MySQL server spuštěn?
2. Jsou správné přihlašovací údaje v `config/env.php`?
3. Existuje databáze `marcelmiler`?

### Problém: „Undefined index" chyby

Zkontrolujte:
1. Je přihlášený trenér/admin?
2. Je v URL parametr `id`?
3. Patří data přihlášenému uživateli?

### Problém: Fotografie se neukládají

Zkontrolujte:
1. Složka `uploads/` má práva na zápis (chmod 755)?
2. Je volba souboř v HTML formuláři?
3. Je velikost souboru pod limitem?

### Problém: E-maily se neposílají

Zkontrolujte:
1. Je SMTP konfigurován v `config/env.php`?
2. Jsou správné přihlašovací údaje?
3. Port 587 (TLS) je otevřen?

---

## 📚 Programátor – úpravy kódu

### Přidání nového cvičení

V `admin/exercises.php` vytvořte formulář a uložte do tabulky `exercises`:

```php
$type = $_POST['type']; // 'standard', 'golf', 'run_outdoor', 'run_treadmill'
$stmt = $pdo->prepare("INSERT INTO exercises (name, type) VALUES (?, ?)");
$stmt->execute([$_POST['name'], $type]);
```

### Přidání nového speciálního sportu

1. Vytvořte tabulku (`sql/schema.sql`)
2. Vytvořte stránky (`training_newsport_start.php`, `training_newsport_detail.php`)
3. Přidejte typ do `exercises.type` enum
4. Přidejte API endpointy (`api/save_newsport_draft.php`)

### Přidání Captcha, Two-factor auth

1. Integrujte knihovnu (npm/composer)
2. Upravte `login.php`, `login_admin.php`
3. Přidejte ověření v `includes/auth.php`

---

## 📞 Podpora a Contributing

Máte nápad na zlepšení? Nahlaste issue v systému projektu nebo kontaktujte správce.

---

**Poslední aktualizace**: Květen 2026  
**Verze**: 1.2.0 (s katalogem sportovních míst a hromadnými operacemi)
