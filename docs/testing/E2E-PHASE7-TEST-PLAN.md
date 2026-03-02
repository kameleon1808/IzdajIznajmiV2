# E2E Test Plan — Security Phase 7: Dependency & Code Hygiene

Verifikacijski test plan za Phase 7. Ova faza ne dodaje nove UI ekrane — testovi provjeravaju da li **CI security gate-ovi rade ispravno**, da li su **tajne zaštićene** i da li **lokalni audit alati** daju očekivane rezultate.

**Preduvjeti:**
- Pristup GitHub repozitoriju (Actions tab)
- Lokalno: `composer` dostupan u `backend/`, `npm` dostupan u `frontend/`
- Lokalno: Docker ili direktan pristup backend-u za Artisan komande

---

## T-01 — Composer audit u CI prolazi na čistom kodu

**Cilj**: Provjera da `composer audit` korak postoji u backend CI i da prolazi.

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | Na GitHubu: otvori **Actions → Backend CI**, posljednji run na `main` | Workflow run vidljiv |
| 2 | Otvori step **Security audit (Composer)** | Step je izvršen (nije skipnut) |
| 3 | Provjeri output step-a | Ili `"No security vulnerability advisories found"`, ili lista advisorija s exit kodom ≠ 0 |

---

## T-02 — Composer audit blokira pipeline na kritičnoj ranjivosti

**Cilj**: Provjera da pipeline pada ako postoji advisory u production dependency-ima.

**Napomena**: Testira se lokalno — ne commitovati pokvarenu `composer.json`.

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | Lokalno, u `backend/`: pokreni `composer audit --no-dev --format=plain` | Ako nema advisorija: exit 0, poruka "No security vulnerability advisories found" |
| 2 | (Simulacija) Privremeno dodaj poznati ranjivi paket (npr. starija verzija), pokreni `composer audit` ponovo | Exit kod ≠ 0, ispisan advisory s CVE brojem i affected verzijom |
| 3 | Vrati `composer.json` na original | — |

---

## T-03 — npm audit u CI prolazi na čistom kodu

**Cilj**: Provjera da `npm audit` korak postoji u frontend CI i da prolazi.

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | Na GitHubu: otvori **Actions → Frontend CI**, posljednji run na `main` | Workflow run vidljiv |
| 2 | Otvori step **Security audit (npm)** | Step je izvršen |
| 3 | Provjeri output step-a | Ili `"found 0 vulnerabilities"`, ili lista s `high`/`critical` koja blokira pipeline |

---

## T-04 — npm audit lokalno

**Cilj**: Provjera lokalnog alata za frontend vulnerability scanning.

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | U `frontend/`: pokreni `npm audit --omit=dev` | Ispis: broj ranjivosti po severity (low/moderate/high/critical) |
| 2 | Pokreni `npm audit --audit-level=high --omit=dev` | Exit 0 ako nema high/critical; exit ≠ 0 ako ima |
| 3 | (Opcionalno) Pokreni `npm audit --audit-level=high` (uključuje devDependencies) | Može biti razlika — devOnly ranjivosti nisu blokirajuće za produkciju |

---

## T-05 — Gitleaks workflow detektuje tajne u PR-u

**Cilj**: Provjera da Gitleaks workflow postoji i da skenira push/PR.

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | Na GitHubu: otvori **Actions → Security Scanning**, posljednji run | Prikazana dva joba: `Gitleaks — secrets detection` i `Verify .env files are not tracked` |
| 2 | Otvori job `Gitleaks — secrets detection`, provjeri log | `"X commits scanned"`, `"leaks found: 0"` (ili `"leaks found: N"` s detaljima ako postoje) |
| 3 | (Simulacija — na feature grani, NIKAD na main) Commituj fajl s lažnim sekretom oblika `password=abc123secret`, puši granu, otvori PR | Gitleaks job pada, GitHub Actions prikazuje `❌ leaks found` |
| 4 | Ukloni fajl, force-puši granu | Gitleaks ponovo prolazi |

---

## T-06 — Env guard blokira commitovane .env fajlove

**Cilj**: Provjera da CI job `env-files-not-committed` detektuje tracking .env fajlova.

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | Na GitHubu: otvori job `Verify .env files are not tracked` u **Security Scanning** workflowu | Step `Fail if any real .env file is tracked by git` je izvršen |
| 2 | Provjeri output step-a | `"No real .env files are tracked. ✓"`, exit 0 |
| 3 | Lokalno: provjeri da su env fajlovi gitignored | `git check-ignore -v backend/.env backend/.env.production frontend/.env` — svaki fajl treba biti naveden u `.gitignore` outputu |
| 4 | (Simulacija) Pokreni `git ls-files backend/.env` | Prazan output — fajl nije trackovan |

---

## T-07 — Dependabot konfiguracija je aktivna

**Cilj**: Provjera da Dependabot konfiguracija postoji i da GitHub prepoznaje ekosisteme.

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | Na GitHubu: otvori **Insights → Dependency graph → Dependabot** | Prikazani ekosistemi: `composer` (`/backend`), `npm` (`/frontend`), `github-actions` (`/`) |
| 2 | Provjeri da status nije `"Dependabot alerts disabled"` | Alertovi uključeni — Dependabot prati ranjivosti |
| 3 | Lokalno: provjeri sadržaj `.github/dependabot.yml` | Tri `package-ecosystem` sekcije: `composer`, `npm`, `github-actions`; schedule `weekly`, timezone `Europe/Belgrade` |
| 4 | (Nakon prvog ponedjeljka od merge-a) Provjeri **Pull requests** tab | Dependabot je kreirao PR-ove za dostupne update-ove |

---

## T-08 — Pre-release security scan (lokalni runbook)

**Cilj**: Provjera da kompletan lokalni sigurnosni scan prolazi bez grešaka.

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | `cd backend && composer audit --no-dev` | Exit 0, nema advisorija |
| 2 | `cd frontend && npm audit --omit=dev --audit-level=high` | Exit 0, nema high/critical |
| 3 | Provjeri da nema trackovanog .env fajla: `git ls-files \| grep -E '^(backend/\.env\|frontend/\.env\|\.env\.production)'` | Prazan output |
| 4 | `cd backend && php artisan test` | Svi testovi prolaze |
| 5 | `cd frontend && npm run test && npm run build` | Testovi i build prolaze |
