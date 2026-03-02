# E2E Test Plan — Security Phase 6: Monitoring & Detection

Verifikacijski test plan za sve što je implementirano u Phase 6.
Phase 6 ne dodaje nove UI ekrane — testovi provjeravaju da li normalni tokovi **generišu očekivane strukturirane logove, fraud signale, Sentry kontekst i health/backup odgovore**.

**Preduvjeti:**
- Docker Compose stack pokrenut: `docker compose up -d`
- Test korisnici (kreirati prije testa):
  - `seeker@test.com` / `Password1!` — uloga: seeker
  - `landlord@test.com` / `Password1!` — uloga: landlord
  - `admin@test.com` / `Password1!` — uloga: admin (MFA omogućen)

> **Napomena**: `storage/` je Docker named volume (`backend-storage`), nije bind mount.
> Folder `backend/storage/logs` na hostu je **prazan** — logovi se nalaze unutar kontejnera.

**Kako pratiti strukturirani log (pokrenuti u zasebnom terminalu):**
```bash
docker compose exec backend tail -f storage/logs/structured-$(date +%F).log
```

Svaki entry je JSON objekt. Relevantna polja: `action`, `user_id`, `ip`, `security_event`.

---

## T-01 — Logovanje neuspješne prijave (auth.login_failed)

**Cilj**: Provjera da neuspješna prijava generiše strukturirani log entry.

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | Otvori `/login` | Formular za prijavu |
| 2 | Unesi `seeker@test.com` + pogrešna lozinka, klikni **Prijavi se** | Greška "Invalid credentials" na stranici |
| 3 | Provjeri strukturirani log | Entry s `"action": "auth.login_failed"`, `"security_event": true`, `"attempt_count": 1`, `"ip": "<tvoja IP>"` |

---

## T-02 — Logovanje brute-force lockout-a (auth.brute_force_lockout)

**Cilj**: Provjera da 10 uzastopnih neuspješnih pokušaja generiše lockout log.

**Napomena**: `LOGIN_MAX_ATTEMPTS=10` (može se privremeno smanjiti na 3 za testiranje).

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | Ponovi neuspješnu prijavu za isti email 10× | Nakon 10. pokušaja: greška "Too many login attempts" |
| 2 | Provjeri strukturirani log | Entry s `"action": "auth.brute_force_lockout"`, `"security_event": true`, `"attempt_count": 10` |
| 3 | Pokušaj se odmah ponovo prijaviti (čak i s ispravnom lozinkom) | Entry s `"action": "auth.login_blocked"`, `"security_event": true` |

---

## T-03 — Logovanje neuspješnog MFA (auth.mfa_failed)

**Preduvjet**: `admin@test.com` ima uključen MFA.

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | Prijavi se s `admin@test.com` | Prikazan MFA challenge ekran |
| 2 | Unesi pogrešan 6-cifreni kod, klikni **Potvrdi** | Greška "Invalid MFA code" |
| 3 | Provjeri strukturirani log | Entry s `"action": "auth.mfa_failed"`, `"security_event": true`, `"used_recovery_code": false`, `"user_id": <admin ID>` |
| 4 | Ponovi s recovery kodom koji ne postoji | Entry s `"used_recovery_code": true` |

---

## T-04 — Logovanje brisanja naloga (auth.account_deleted)

**Preduvjet**: Kreirati test korisnika `delete_me@test.com` / `Password1!`.

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | Prijavi se kao `delete_me@test.com` | Uspješna prijava |
| 2 | Idi na **Postavke → Nalog** | Sekcija za brisanje naloga |
| 3 | Klikni **Obriši nalog**, unesi lozinku `Password1!`, potvrdi | Odjava i preusmjeren na `/` |
| 4 | Provjeri strukturirani log | Entry s `"action": "auth.account_deleted"`, `"security_event": true`, `"user_id": <ID>` |

---

## T-05 — Logovanje opoziva sesije (auth.session_revoked)

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | Prijavi se kao `seeker@test.com` u **dva različita browsera/incognito** | Oba prijavljena |
| 2 | U browseru A: idi na **Postavke → Sigurnost → Aktivne sesije** | Lista sesija — vidljive 2+ sesije |
| 3 | Klikni **Odjavi** pored sesije iz browsera B | Sesija uklonjena iz liste |
| 4 | Provjeri strukturirani log | Entry s `"action": "auth.session_revoked"`, `"security_event": true`, `"self_revocation": false` |

---

## T-06 — Logovanje masovnog opoziva sesija (auth.sessions_bulk_revoked)

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | Prijavi se kao `seeker@test.com` u 3 browsera/incognito prozora | Sva tri prijavljena |
| 2 | U jednom browseru: idi na **Postavke → Sigurnost**, klikni **Odjavi sve ostale sesije** | Ostale 2 sesije odjavljene |
| 3 | Provjeri u preostalim browserima | Preusmjereni na `/login` |
| 4 | Provjeri strukturirani log | Entry s `"action": "auth.sessions_bulk_revoked"`, `"revoked_count": 2` |

---

## T-07 — Logovanje admin impersonacije (auth.impersonation_started / stopped)

**Preduvjet**: Admin impersonation feature je dostupan iz admin panela.

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | Prijavi se kao `admin@test.com` (sa MFA) | Admin dashboard |
| 2 | Idi na **Admin → Korisnici**, pronađi `seeker@test.com`, klikni **Impersonuj** | Prijavljen kao seeker, banner "Impersonuješ korisnika X" |
| 3 | Provjeri strukturirani log | Entry s `"action": "auth.impersonation_started"`, `"admin_id": <admin ID>`, `"target_user_id": <seeker ID>` |
| 4 | Klikni **Završi impersonaciju** | Vraćen na admin nalog |
| 5 | Provjeri strukturirani log | Entry s `"action": "auth.impersonation_stopped"` |

---

## T-08 — Logovanje admin pristupa KYC dokumentu (kyc.document_accessed_by_admin)

**Preduvjet**: Seeker je uploadovao KYC dokument.

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | Prijavi se kao `admin@test.com` | Admin dashboard |
| 2 | Idi na **Admin → KYC** | Lista KYC podnesaka |
| 3 | Otvori podnesak `seeker@test.com`, klikni na dokument (sliku/PDF) | Dokument prikazan |
| 4 | Provjeri strukturirani log | Entry s `"action": "kyc.document_accessed_by_admin"`, `"security_event": true`, `"admin_id": <admin ID>`, `"owner_id": <seeker ID>` |

---

## T-09 — Health endpoint: provjera storage diska

**Cilj**: Provjera da `/health/ready` uključuje status storage diskova (dodata u Phase 6).

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | Otvori u browseru ili `curl`: `http://localhost:8000/api/v1/health/ready` | JSON odgovor |
| 2 | Provjeri polje `checks.storage` u odgovoru | `{"ok": true, "disks": {"private": true, "public": true}}` |
| 3 | (Opcionalno) Privremeno oduzmi dozvole na storage direktoriju, ponovi zahtjev | `{"ok": false, ...}`, HTTP status 503 |

**Primjer očekivanog odgovora:**
```json
{
  "status": "ok",
  "checks": {
    "database": true,
    "cache": true,
    "queue": { "ok": true, "failed_jobs": 0 },
    "storage": { "ok": true, "disks": { "private": true, "public": true } }
  }
}
```

---

## T-10 — Backup verification komanda

**Cilj**: Provjera `php artisan backup:verify` komande.

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | Pokušaj bez backup direktorija: `php artisan backup:verify --backup-dir=/tmp/nonexistent` | Greška: "Backup directory does not exist", exit code 1 |
| 2 | Kreiraj testni backup: `mkdir -p /tmp/test_backups && gzip -c /dev/null > /tmp/test_backups/test.sql.gz` | Fajl kreiran |
| 3 | Pokreni: `php artisan backup:verify --backup-dir=/tmp/test_backups` | "Backup OK — test.sql.gz (0.0 hours old)" |
| 4 | Provjeri strukturirani log | Entry s `"action": "backup.verified"`, `"backup_file": "test.sql.gz"` |
| 5 | Stvori stari backup: `touch -d '2 days ago' /tmp/test_backups/old.sql.gz` i ukloni novi | Greška: "Latest backup is stale", exit code 1 |
| 6 | Provjeri strukturirani log za stale backup | Entry s `"action": "backup.stale"`, `"security_event": true` |

---

## T-11 — Sentry user context (backend middleware)

**Preduvjet**: Sentry je konfigurisan (`SENTRY_ENABLED=true`, validan `SENTRY_DSN`) — **testirati samo u staging okruženju**.

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | Prijavi se kao `seeker@test.com` | Uspješna prijava |
| 2 | Izazovi Sentry error (npr. pristup nepostojećem resursu koji baca exception) | Error pojavljuje se u Sentry dashboardu |
| 3 | Otvori event u Sentry UI | Polje `user.id` sadrži seeker ID, `user.segment` sadrži "seeker" — **bez emaila, bez telefona** |

---

## T-12 — Fraud signal: KYC multi-user IP

**Cilj**: Provjera da KYC podnošenje s iste IP adrese za 2+ korisnika aktivira fraud signal.

**Napomena**: `FRAUD_SIGNAL_KYC_MULTI_USER_IP_THRESHOLD=2` (zadana vrijednost).

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | Prijavi se kao `seeker@test.com`, podnesi KYC dokument | KYC podnesak kreiran |
| 2 | Odjavi se, prijavi se kao `landlord@test.com` (ista IP), podnesi KYC | KYC podnesak kreiran |
| 3 | Provjeri strukturirani log | Entry s `"action": "fraud.signal_recorded"`, `"signal": "kyc_multi_user_ip"`, `"ip_hash": "..."` |
| 4 | Provjeri admin notifikacije (Admin → Notifikacije) | Nova notifikacija o fraud signalu za `landlord@test.com` |
| 5 | Provjeri fraud score za `landlord@test.com` u admin panelu | Score povećan za 20 bodova |

---

## T-13 — Fraud signal: rapid attachment uploads

**Cilj**: Provjera da prekoračenje limita za upload attachmenta aktivira fraud signal.

**Napomena**: `CHAT_ATTACHMENTS_PER_10_MINUTES=10` — privremeno smanjiti na 2 za testiranje.

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | Prijavi se kao `seeker@test.com`, otvori chat thread | Chat interfejs |
| 2 | Uploaduj attachment 3× u kratkom roku (ako je limit=2) | Treći upload odbijen s greškom o rate limitu |
| 3 | Provjeri strukturirani log | Entry s `"action": "fraud.signal_recorded"`, `"signal": "rapid_uploads"`, `"user_id": <seeker ID>` |
| 4 | Provjeri fraud score za `seeker@test.com` u admin panelu | Score povećan za 5 bodova |
