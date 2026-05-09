# Safe local/production switching

This project uses profile files in config for safe switching without committing secrets.

## Files

- config/env.php: active runtime configuration (ignored by git)
- config/env.local.php: local profile (ignored by git)
- config/env.production.php: production profile (ignored by git)
- config/env.example.php: template (versioned)

## First-time setup

1. Copy config/env.example.php to config/env.local.php and fill local values.
2. Create config/env.production.php with production values.
3. Never commit any env.*.php files except config/env.example.php.
4. In local profile, do not define BASE_URL unless you really need forced value.

## Switch profile (Windows PowerShell)

From project root:

```powershell
.\scripts\switch-env.ps1 -Profile local
```

Switch back to production profile:

```powershell
.\scripts\switch-env.ps1 -Profile production
```

The script copies selected profile to config/env.php.

Tip: for project in subfolder (for example /marcelmiler), leave BASE_URL undefined to use automatic detection.

## Deployment safety

- Do development only with local profile active.
- Deploy only code that has passed local tests.
- Database changes: add SQL migration to sql/ and apply once on server during deployment.
