# Husky — Kurz‑Checkliste für das Team

Zweck
--
Damit alle lokalen Commits automatisch überprüft werden (z. B. Conventional Commits). Das Repo nutzt ein zentrales Prüfskript `.githooks/commit-msg`.

Voraussetzungen
--
- Node.js & npm installiert
- Git installiert (auf Windows: Git Bash empfohlen)

Schnellstart (einmalig, im Repo‑Root)
--
1. Wechsel in das Laravel‑Frontend:

```bash
cd v4
```

2. Installiere Abhängigkeiten und aktiviere Husky (prepare läuft automatisch):

```bash
npm install
# oder falls nötig:
npx husky install
npx husky add .husky/commit-msg 'sh ../.githooks/commit-msg "$1"'
```

Verifizieren
--
1. Lege einen Test‑Commit an (leer) mit gültiger Commit‑Message:

```bash
git commit --allow-empty -m "fix(ci): test husky"
```

2. Wenn der Commit akzeptiert wird, ist Husky aktiv.

Häufige Probleme & Lösungen
--
- Hook blockiert Commit wegen Nachricht: Nutze das Format `type(scope): beschreibung` (z. B. `fix(api): handle null`).
- Auf Windows fehlt `sh`: benutze Git Bash oder WSL, oder führe Hook mit `bash` aus.
- Hooks aktivieren: `git config core.hooksPath .husky` im Repo‑Root setzen.
- Prepare Script wurde nicht ausgeführt: `npm run prepare` manuell aufrufen.
- Lokale Deaktivierung (nur kurzfristig): `git config --unset core.hooksPath` (Vorsicht: dann laufen keine Hooks).

Debugging
--
- Script manuell testen:

```bash
sh .githooks/commit-msg "fix: test"
```

- Prüfe Dateiberechtigungen auf *nix: `chmod +x v4/.husky/commit-msg`.

Empfehlung für Team
--
- Jeder Entwickler führt `npm install` in `v4` aus, damit Husky via `prepare` aktiviert wird.
- Dokumentiere diese Checkliste in Onboarding‑Guides.

Kontakt
--
Bei Problemen: @Repo‑Maintainer oder öffne ein Issue mit dem Tag `infra/hooks`.
