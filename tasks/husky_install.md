# Husky: Installation und Setup

Dieses Dokument beschreibt, wie Husky lokal eingerichtet wird, damit Git‑Hooks (z. B. `commit-msg`) automatisch laufen.

Empfohlene Schritte (im Repo‑Root):

1. Node.js und npm müssen installiert sein.

2. Dev‑Dependency installieren:

```bash
npm install --save-dev husky
```

3. Husky initialisieren (erstellt `.husky/` und aktiviert Hooks):

```bash
npx husky install
```

4. Falls noch nicht vorhanden, füge in `package.json` ein `prepare` Script hinzu, damit Husky nach `npm install` automatisch aktiviert wird:

```json
"scripts": {
  "prepare": "husky install"
}
```

5. Hook installieren (falls du die `.husky/commit-msg` nicht manuell erstellt hast):

```bash
npx husky add .husky/commit-msg 'sh .githooks/commit-msg "$1"'
```

PowerShell (einfach):

```powershell
git config core.hooksPath .husky
npx husky install
npx husky add .husky/commit-msg 'sh .githooks/commit-msg "$1"'
```

Hinweise:
- Das Repository enthält bereits ein Prüfscript unter `.githooks/commit-msg`, welches hier wiederverwendet wird.
- Teammitglieder sollten `npm install` ausführen, damit Husky durch das `prepare` Script aktiviert wird.
