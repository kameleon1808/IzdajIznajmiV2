# UAT scenariji — Faza 4: Zaštita fajlova i kontrola pristupa

Ovaj dokument je namenjen testerima i product ownerima.
Nije potrebno tehničko znanje — sve se radi kroz UI ili browser adresnu traku.

---

## Priprema

- Otvori aplikaciju: `http://localhost:5173`
- Koristi **dva različita browser-a** (npr. Chrome i Firefox) ili dva Chrome profila da simuliraš dva korisnika istovremeno
- Resetuj bazu pre testiranja: zamoli developera da pokrene `php artisan migrate:fresh --seed`

### Test korisnici (lozinka za sve: `password`)

| Ko | Email | Uloga |
|---|---|---|
| Admin | `admin@example.com` | administrator |
| Lana | `lana@demo.com` | landlord (ima KYC dokumenta) |
| Tomas | `tomas@demo.com` | tenant (nema veze sa Laninim dokumentima) |
| Tena | `tena@demo.com` | tenant (razgovara sa Lanom u chatu) |

---

## Scenario 1 — Niko ne može da otvori tuđi KYC dokument

**Cilj:** Tomas ne sme da preuzme Lanine lične dokumente čak ni ako zna link.

### Koraci

**Browser A — Prijava kao Lana:**

1. Otvori `http://localhost:5173` i prijavi se kao `lana@demo.com`
2. Idi na stranicu za verifikaciju profila (Profil → Verifikacija identiteta)
3. Lana ima uploadovane dokumente — zatraži od developera ID prvog dokumenta (broj u tabeli `kyc_documents`) ili ga pronađi u URL-u dok pregledaš stranicu
4. Otvori u browser-u: `http://localhost:8000/api/v1/kyc/documents/{ID}`

   **Očekivano:** dokument se preuzima ili prikazuje u browser-u ✅

**Browser B — Prijava kao Tomas:**

5. Prijavi se kao `tomas@demo.com`
6. U istom browser-u B upiši isti URL sa Laninim ID-em dokumenta: `http://localhost:8000/api/v1/kyc/documents/{ID}`

   **Očekivano:** Pojavljuje se greška `403 Forbidden` — fajl se ne prikazuje ✅

   **Neuspeh ako:** Tomasu se prikaže Lanin dokument ❌

---

## Scenario 2 — Gost (nije prijavljen) ne može da otvori KYC dokument

**Cilj:** Svaki pokušaj pristupa dokumentu bez prijave mora biti odbijen.

### Koraci

1. Otvori incognito/private prozor (Ctrl+Shift+N u Chrome-u)
2. Upiši direktan URL: `http://localhost:8000/api/v1/kyc/documents/1`

   **Očekivano:** Greška `401 Unauthenticated` ili redirect na login ✅

   **Neuspeh ako:** Fajl se prikazuje ❌

---

## Scenario 3 — Direktan link na storage mapu ne radi

**Cilj:** Čak i ako neko pogodi tačnu putanju fajla na serveru, Nginx mora da ga blokira.

### Koraci

1. U bilo kom browser-u (prijavljen ili ne) upiši:
   `http://localhost:8000/storage/app/private/kyc/1/1/id_front.jpg`
2. Proba i sa: `http://localhost:8000/storage/app/kyc/1/1/selfie.jpg`

   **Očekivano:** `404 Not Found` — stranica ili greška, nikako slika ✅

   **Neuspeh ako:** Slika ili PDF se prikaže ❌

3. Proveri da javne slike još uvek rade:
   `http://localhost:8000/storage/listings/neka-slika.jpg`

   **Očekivano:** Slika se prikazuje normalno ✅

---

## Scenario 4 — Niko ne može da otvori fajl iz tuđeg chat razgovora

**Cilj:** Tomas ne sme da preuzme fajl koji je Tena poslala Lani u privatnom chatu.

### Koraci

**Browser A — Lana šalje fajl Teni:**

1. Prijavi se kao `lana@demo.com`
2. Otvori chat sa Tenom
3. Pošalji poruku sa prilogom (slika ili PDF)
4. Kada se poruka pošalje, desni klik na prilog → "Kopiraj adresu linka" ili pronađi ID u Developer Tools-u (Network tab)
   — tražiš URL oblika `/api/v1/chat/attachments/{ID}`

**Browser B — Tomas pokušava isti link:**

5. Prijavi se kao `tomas@demo.com`
6. Upiši URL prilog koji si kopirao: `http://localhost:8000/api/v1/chat/attachments/{ID}`

   **Očekivano:** Greška `403 Forbidden` ✅

   **Neuspeh ako:** Fajl se preuzima ❌

7. Proba isto sa thumbnail URL-om: `http://localhost:8000/api/v1/chat/attachments/{ID}/thumb`

   **Očekivano:** Ista `403` greška ✅

---

## Scenario 5 — Samo učesnik razgovora vidi prilog

**Nastavak scenarija 4, isti fajl:**

1. Ostani prijavljen kao `tena@demo.com` (Browser koji nije Tomas)
2. Otvori chat sa Lanom
3. Nađi poruku sa prilogom i klikni da je preuzmem

   **Očekivano:** Fajl se preuzima normalno ✅

---

## Scenario 6 — Landlord ne može da odobri KYC prijavu

**Cilj:** Samo admin ima pristup admin panelu za KYC.

### Koraci

1. Prijavi se kao `lana@demo.com`
2. U browser-u upiši direktno:
   `http://localhost:8000/api/v1/admin/kyc/submissions/1/approve`
   (PATCH metoda — lakše testirati kroz Postman ili DevTools Console):
   ```
   fetch('/api/v1/admin/kyc/submissions/1/approve', {method:'PATCH', headers:{'Accept':'application/json'}})
     .then(r => r.json()).then(console.log)
   ```

   **Očekivano:** `403 Forbidden` ✅

   **Neuspeh ako:** Status prijave se promeni u `approved` ❌

---

## Scenario 7 — Admin odobrava KYC prijavu, korisnikov profil se ažurira

**Cilj:** Admin sa potvrđenim MFA može odobriti prijavu i to se odmah vidi na profilu korisnika.

### Koraci

**Priprema — prijava admina:**

1. Prijavi se kao `admin@example.com`
2. Sistem će tražiti MFA kod — unesi kod iz authenticator aplikacije
3. Nakon uspešne prijave, idi na Admin panel → KYC Submissions (ili direktno: `http://localhost:5173/admin/kyc`)

**Odobravanje:**

4. Pronađi prijavu od `lana@demo.com` sa statusom `pending`
5. Klikni "Approve" (ili "Odobri")

   **Očekivano:** Status se menja u `approved`, pojavljuje se poruka o uspehu ✅

**Provera na Laninom profilu:**

6. Prijavi se kao `lana@demo.com` (drugi browser)
7. Otvori Profil → vidljiv status verifikacije

   **Očekivano:** Status je `Verified` / `Odobren`, ikonica verifikacije je zelena ✅

   **Neuspeh ako:** Status ostaje `pending` ❌

---

## Scenario 8 — devCode nije vidljiv u produkciji

**Cilj:** Kod za verifikaciju email-a koji pomaže developerima ne sme da se prikaže u produkcijskom modu.

> Ovaj scenario zahteva kratku promenu konfiguracije — zamoli developera da postavi `APP_ENV=production` u `.env` i restartuje server, pa da ga vrati na `local` nakon testa.

### Koraci

1. Kreiraj novi test nalog sa nepostojećim emailom (ili koristi nalog koji nije verifikovan)
2. Prijavi se i triggeriraj slanje verifikacionog emaila
3. Otvori DevTools (F12) → Network tab → pronađi odgovor na `/api/v1/me/verification/email/request`
4. Pogledaj JSON body odgovora

   **Očekivano (production):** U JSON-u **nema** polja `devCode` ✅

   **Neuspeh ako:** U odgovoru se vidi `"devCode": 123456` ❌

5. Ponovi isti korak sa `APP_ENV=local`

   **Očekivano (local):** `devCode` je prisutan u odgovoru — to je normalno i namerno za development ✅

---

## Rezultati testiranja

| ID | Scenario | Rezultat | Napomena |
|---|---|---|---|
| S1 | Tomas ne može da otvori Lanin KYC dokument | ☐ PASS / ☐ FAIL | |
| S2 | Gost ne može da otvori KYC dokument | ☐ PASS / ☐ FAIL | |
| S3 | Direktan storage URL blokiran (Nginx) | ☐ PASS / ☐ FAIL / ☐ N/A (bez Nginx-a) | |
| S4 | Tomas ne može da otvori prilog iz tuđeg chata | ☐ PASS / ☐ FAIL | |
| S5 | Učesnik razgovora normalno vidi prilog | ☐ PASS / ☐ FAIL | |
| S6 | Landlord dobija 403 na admin KYC endpointu | ☐ PASS / ☐ FAIL | |
| S7 | Admin odobrava KYC, profil se ažurira | ☐ PASS / ☐ FAIL | |
| S8 | devCode nije u produkcijskom odgovoru | ☐ PASS / ☐ FAIL | |

**Faza 4 je prošla ako:** svi scenariji od S1 do S7 su PASS (S3 može biti N/A ako se ne koristi Nginx).
