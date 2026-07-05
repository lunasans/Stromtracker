---
title: "Onboarding: Neuer Entwickler-Check"
labels: onboarding, infra
assignees: ''
---

## Onboarding: Neuer Entwickler — Checkliste

Bitte durchgehen und abhaken. Ziel: lokale Entwicklungsumgebung intakt und Husky aktiv.

- [ ] Repository klonen
  - `git clone <repo-url>`
- [ ] Composer & npm installieren (im `v4`-Ordner)
  - `cd v4`
  - `composer install`
  - `npm install`
- [ ] Datenbank importieren (Test‑DB)
  - `mysql -u user -p < sql/stromtracker.sql`
- [ ] `.env` konfigurieren (aus `.env.example` kopieren)
- [ ] Husky aktivieren
  - `cd v4`
  - `npm install`
  - `npm run prepare`  # oder `npx husky install` falls erforderlich
  - Optional: `npx husky add .husky/commit-msg 'sh ../.githooks/commit-msg "$1"'`
- [ ] Test‑Commit durchführen
  - `git commit --allow-empty -m "fix(ci): test husky"`
- [ ] Lokalen Server starten und Grundfunktionen prüfen
  - `php artisan serve` (im `v4`-Ordner)

## Hinweise
- Siehe `tasks/husky_readme.md` für Troubleshooting bei Hooks.
- Wenn Probleme auftreten, poste Fehlermeldungen in diesem Issue.
