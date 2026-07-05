# Git Hooks: Installation

Dieses Repo enthält ein `commit-msg` Hook in `.githooks/commit-msg`, das Conventional Commits erzwingt.

Empfohlene Schritte zur Aktivierung (lokal):

1) Setze den Hooks‑Pfad (einmalig im Repo):

```bash
git config core.hooksPath .githooks
```

2) Prüfe, ob das `commit-msg` Script ausführbar ist (unter Windows ggf. Git Bash verwenden):

```bash
ls -l .githooks/commit-msg
```

3) Alternative (PowerShell) — Script ausführen, um den Hook‑Pfad zu setzen:

```powershell
.
Set-Location -Path (Resolve-Path ..) # im Repo-Root ausführen
git config core.hooksPath .githooks
```

Hinweis:
- Teammitglieder sollten ebenfalls `git config core.hooksPath .githooks` ausführen, damit Hooks lokal greifen.
- Wenn ihr ein Hook‑Manager wie Husky bevorzugt, kann das Template angepasst werden.
