---
name: Team Onboarding Checklist
about: Ticket / Checkliste für neue Teammitglieder, um schnell produktiv zu werden
title: "Onboarding: Neuer Teammitglied-Check"
labels: onboarding, infra
assignees: ''
---

## Ziel
Dieses Issue begleitet neue Entwickler beim lokalen Setup des Projekts und stellt sicher, dass wichtige Tools (z. B. Husky) aktiviert sind.

## Aufgaben (bitte abarbeiten)
- [ ] Repository klonen
  - `git clone <repo-url>`
- [ ] In `v4` Composer & npm Abhängigkeiten installieren
  - `cd v4`
  - `composer install`
  - `npm install`
- [ ] Datenbank importieren (Test‑DB)
  - `mysql -u user -p < sql/stromtracker.sql`
- [ ] Umgebungsdateien einrichten
  - `.env` aus `.env.example` kopieren und DB‑Zugang anpassen
- [ ] Husky aktivieren (lokal)
  - `cd v4`
  - `npm install` (oder `npm run prepare`)
  - Optional: `npx husky install` & `npx husky add .husky/commit-msg 'sh ../.githooks/commit-msg "$1"'`
- [ ] Test‑Commit durchführen um Hooks zu prüfen
  - `git commit --allow-empty -m "fix(ci): test husky"`
- [ ] Lokalen Server/Jobs starten (optional)
  - `php artisan serve` (im `v4`-Ordner)
- [ ] Mindestens ein Feature manuell testen (z. B. Login, Telegram Webhook)

## Hinweise
- Lies `tasks/husky_readme.md` für Troubleshooting bei Hooks.
- Bei Problemen: markiere das Issue und beschreibe Fehlermeldungen / Betriebssystem.

## Kontakt / Reviewer
- @Repo-Maintainer (bitte hier Slack/Telegram/Email ergänzen)
