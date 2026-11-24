# Publishing VinMail SDKs (Step-by-Step Guide)

All four package registries (**npm**, **Packagist**, **PyPI**, and **pub.dev**) are **100% free** for public open-source packages.

> **Tip:** Before publishing, push this `vinmail-sdks` folder to a **public GitHub repository** (for example `https://github.com/vinworld/vinmail-sdks`). Packagist pulls directly from GitHub, and npm/PyPI/pub.dev all link back to your repository.
>
> **Important for Packagist (PHP):** Packagist expects `composer.json` at the root of the repository it tracks. Either push `php/` as its own dedicated GitHub repo (`github.com/vinworld/vinmail-php`) OR use a GitHub split action. Pushing `php/` to `github.com/vinworld/vinmail-php` takes 1 minute and is the cleanest setup!

---

## 1. Node.js / TypeScript — npm (`@vinworld/vinmail`)

### Create account & scope (one-time)
1. Go to **https://www.npmjs.com/signup** and create a free account (enable 2FA with your authenticator app).
2. Either choose username **`vinworld`** (which automatically owns the `@vinworld` scope), OR create a free public Organization named **`vinworld`** under **Account → Add Organization → Create (Free / Unlimited public packages)**.
3. Verify your email address (`dev@vinworlddev.com`).

### Publish from PowerShell
```powershell
cd "C:\Users\PC\Desktop\workingProject\vinmail-project\vinmail-sdks\node"
npm.cmd login
npm.cmd test
npm.cmd publish --access public
```

Developers can immediately install via:
```bash
npm install @vinworld/vinmail
```

---

## 2. PHP / Laravel — Packagist (`vinworld/vinmail`)

### Create GitHub repo & Packagist account (one-time)
1. Create a public GitHub repo named **`vinmail-php`** (e.g. `https://github.com/vinworld/vinmail-php`) containing the contents of `vinmail-sdks/php/` at its root (`composer.json`, `src/`, `tests/`, `README.md`, `LICENSE`).
2. Tag your first release in Git:
   ```powershell
   cd "C:\Users\PC\Desktop\workingProject\vinmail-project\vinmail-sdks\php"
   git init
   git add .
   git commit -m "Initial release v0.1.0"
   git branch -M main
   git remote add origin https://github.com/vinworld/vinmail-php.git
   git push -u origin main
   git tag v0.1.0
   git push origin v0.1.0
   ```
3. Go to **https://packagist.org/register/** and sign up with **Sign in with GitHub** (this auto-syncs future git tags!).
4. Click **Submit** (top bar), paste `https://github.com/vinworld/vinmail-php`, click **Check** → **Submit**.

Developers can immediately install via:
```bash
composer require vinworld/vinmail
```

---

## 3. Python — PyPI (`vinworld-vinmail`)

### Create account & API token (one-time)
1. Go to **https://pypi.org/account/register/** and create a free account (verify email + enable 2FA).
2. Go to **Account Settings → API tokens → Add API token** (scope: *Entire account* for first publish, then restrict to `vinworld-vinmail` afterward). Copy the `pypi-...` token.
3. Make sure Python 3.10+ is installed on your machine (https://www.python.org/downloads/ — check **"Add python.exe to PATH"**).

### Build, test & publish from PowerShell
```powershell
cd "C:\Users\PC\Desktop\workingProject\vinmail-project\vinmail-sdks\python"
python -m unittest discover -s tests
python -m pip install --upgrade build twine
python -m build
python -m twine upload dist/*
```
When Twine prompts:
- **Username:** `__token__`
- **Password:** paste your `pypi-...` API token.

*(Note: if `vinmail` is available on PyPI and you prefer `pip install vinmail`, you can change `name = "vinworld-vinmail"` in `pyproject.toml` to `name = "vinmail"` before building.)*

Developers can immediately install via:
```bash
pip install vinworld-vinmail
```

---

## 4. Dart — pub.dev (`vinmail`)

### Create verified publisher (recommended, one-time)
1. Go to **https://pub.dev** and sign in with your Google account (e.g. linked to `vinworlddev.com`).
2. *(Optional but gives you the blue verified badge!)* Go to **https://pub.dev/create-publisher**, enter `vinworlddev.com`, and verify domain ownership via Google Search Console DNS TXT record.
3. Check if the package name `vinmail` is free at **https://pub.dev/packages/vinmail** (404 = available!). If taken, change `name: vinmail` in `dart/pubspec.yaml` to `name: vinworld_vinmail` and update imports to `package:vinworld_vinmail/vinmail.dart`.

### Dry-run & publish from PowerShell
```powershell
cd "C:\Users\PC\Desktop\workingProject\vinmail-project\vinmail-sdks\dart"
C:\src\flutter\bin\dart.bat analyze --fatal-infos
C:\src\flutter\bin\dart.bat test
C:\src\flutter\bin\dart.bat pub publish --dry-run
C:\src\flutter\bin\dart.bat pub publish
```
The first `dart pub publish` opens your browser to sign in with Google, confirms the package archive, and uploads it live to pub.dev.

Developers can immediately install via:
```bash
dart pub add vinmail
```
