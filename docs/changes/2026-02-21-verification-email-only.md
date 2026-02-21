# Verification Change Log - 2026-02-21 (Email-only scope)

Ovaj dokument pokriva kompletne izmene vezane za verification domen uradjene u fazi "Verification-Fix", ukljucujuci i operativni incident sa SMTP konfiguracijom u production Docker okruzenju.

## 1) Cilj i scope

Cilj faze:
- zadrzati email verification kao aktivan kanal;
- privremeno ukloniti phone verification iz backend i frontend flow-a;
- zadrzati seeker/landlord KYC verification tok nepromenjen;
- ukloniti operativnu konfuziju oko mail konfiguracije u production stack-u.

Out of scope:
- phone verification provideri (WhatsApp/SMS i slicno);
- promene DB semantike oko `phone_verified` kolone (kolona ostaje u modelu/migracijama zbog kompatibilnosti).

## 2) Backend izmene

### 2.1 Verification endpointi
- Uklonjeni phone endpointi:
  - `POST /api/v1/me/verification/phone/request`
  - `POST /api/v1/me/verification/phone/confirm`
- Ostali aktivni email endpointi:
  - `POST /api/v1/me/verification/email/request`
  - `POST /api/v1/me/verification/email/confirm`

Fajl:
- `backend/routes/api.php`

### 2.2 UserVerificationController refactor
- Controller sada podrzava samo email channel.
- Kod se i dalje cuva hash-ovan u `user_verification_codes`.
- Ako delivery padne, verification code zapis se brise i vraca se `503`.
- Dev code (`devCode`) ostaje samo za `local/testing`.

Fajl:
- `backend/app/Http/Controllers/UserVerificationController.php`

### 2.3 Mail delivery sloj
Dodato:
- `EmailVerificationCodeSender` servis za slanje koda putem `Mail` fasade.
- `VerificationCodeMail` mailable.
- `emails/verification-code` view.
- `VerificationDeliveryException`.
- `config/verification.php` sa `VERIFICATION_CODE_TTL_MINUTES`.

Fajlovi:
- `backend/app/Services/Verification/EmailVerificationCodeSender.php`
- `backend/app/Mail/VerificationCodeMail.php`
- `backend/resources/views/emails/verification-code.blade.php`
- `backend/app/Services/Verification/Exceptions/VerificationDeliveryException.php`
- `backend/config/verification.php`

### 2.4 Uklanjanje phone verification domena
- Uklonjena `CHANNEL_PHONE` konstanta iz verification code modela.
- Uklonjen phone verification payload iz API resource odgovora.
- Public profile verifications sada vraca samo `email` i `address`.

Fajlovi:
- `backend/app/Models/UserVerificationCode.php`
- `backend/app/Http/Resources/UserResource.php`
- `backend/app/Http/Resources/PublicUserResource.php`

### 2.5 Rating uslovi
- Rating vise ne zahteva phone verification.
- Novi uslov: `email_verified && address_verified`.
- Validation poruke azurirane na "Verify your email and address to rate".

Fajlovi:
- `backend/app/Services/RatingService.php`
- `backend/app/Services/ListingRatingService.php`

### 2.6 Profile update ponasanje
- Promena broja telefona vise ne resetuje `phone_verified` jer phone verification flow nije aktivan.

Fajl:
- `backend/app/Http/Controllers/UserAccountController.php`

## 3) Frontend izmene

### 3.1 Verification UI
- Uklonjen ceo phone verification blok sa `/profile/verification`.
- Stranica sada prikazuje i obradjuje samo email verification.

Fajl:
- `frontend/src/pages/KycVerification.vue`

### 3.2 API service layer
- Uklonjeni export-i i implementacije:
  - `requestPhoneVerification`
  - `confirmPhoneVerification`

Fajlovi:
- `frontend/src/services/index.ts`
- `frontend/src/services/realApi.ts`
- `frontend/src/services/mockApi.ts`

### 3.3 Profile i tipovi
- Public profile vise ne renderuje phone verification badge.
- Type definition `PublicProfile.verifications` vise nema `phone`.
- Auth store vise ne odrzava `phoneVerified`.

Fajlovi:
- `frontend/src/pages/PublicProfile.vue`
- `frontend/src/types/index.ts`
- `frontend/src/stores/auth.ts`

### 3.4 i18n cleanup
- Uklonjeni phone verification translation kljucevi.
- `verification.codeHint` je email-only.

Fajl:
- `frontend/src/stores/language.ts`

## 4) Testovi i QA update

Backend test update:
- `VerificationApiTest` ostavljen samo za email flow.
- `UserAccountApiTest` prilagodjen novom phone update ponasanju.
- `RatingsApiTest` fixture-i azurirani da ne zavise od `phone_verified=true`.

Fajlovi:
- `backend/tests/Feature/VerificationApiTest.php`
- `backend/tests/Feature/UserAccountApiTest.php`
- `backend/tests/Feature/RatingsApiTest.php`

Dokumentacija test plana:
- uklonjen VER-02 (phone verification scenario).

Fajl:
- `docs/test-plan-sr.md`

## 5) Production incident: mail config nije ucitana

### 5.1 Simptom
Email verification endpoint vraca:
- `503 Email delivery is not configured.`

U runtime proveri:
- `config('mail.default') === 'log'`
- `smtp.host === 127.0.0.1`
- `smtp.port === 2525`

### 5.2 Root cause
U production compose-u je `APP_ENV=production`, pa Laravel ucitava:
- `backend/.env.production` (ako postoji)
a ne `backend/.env`.

`backend/.env.production` je imao stari mail config (`MAIL_MAILER=log`), iako je `backend/.env` bio ispravno podesen na SMTP.

### 5.3 Resolution
1. Uskladiti `backend/.env.production` sa realnim SMTP vrednostima.
2. Recreate backend procesa:
   - backend
   - queue
   - scheduler
   - reverb
3. Odraditi cache clear:
   - `php artisan optimize:clear`
4. Verifikacija:
   - `config('mail.default')` mora biti `smtp`.

## 6) Operativni checklist (mail)

Obavezni kljucevi za email verification:
- `MAIL_MAILER=smtp`
- `MAIL_SCHEME=smtps` (za port 465)
- `MAIL_HOST`
- `MAIL_PORT`
- `MAIL_USERNAME`
- `MAIL_PASSWORD`
- `MAIL_FROM_ADDRESS`
- `MAIL_FROM_NAME`

Brza provera iz running backend kontejnera:
```bash
php artisan tinker --execute="dump(config('mail.default')); dump(config('mail.mailers.smtp.host')); dump(config('mail.mailers.smtp.port'));"
```

Ako nije `smtp`:
```bash
php artisan optimize:clear
```
i recreate backend stack servisa.

## 7) Napomena za buduci razvoj

Ako se phone verification bude vracao:
- vratiti API rute i servisne funkcije;
- vratiti UI elemente i i18n kljuceve;
- jasno odvojiti feature flag (npr. `VERIFICATION_PHONE_ENABLED`) da ukljucivanje/iskljucivanje ne zahteva opsezan cleanup.
