# End-to-End UI Test Plan

Ručni test plan za potpune korisničke tokove kroz sučelje (klik-po-klik).

**Preduvjeti**: backend pokrenut na `localhost:8000`, frontend na `localhost:5173`.
**Test korisnici** (kreirati prije testa):
- `seeker@test.com` / `Password1!` — uloga: seeker
- `landlord@test.com` / `Password1!` — uloga: landlord
- `admin@test.com` / `Password1!` — uloga: admin (MFA omogućen)

---

## T-01 — Registracija novog korisnika

**Uloga**: anonimni posjetitelj
**Stranica**: `/register`

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | Otvori `/register` | Prikazan formular za registraciju |
| 2 | Ostavi sva polja prazna, klikni **Registruj se** | Validacijske greške ispod svakog obaveznog polja |
| 3 | Unesi validne podatke (ime, email, lozinka, uloga: **Stanar**) | — |
| 4 | Klikni **Registruj se** | Preusmjeren na početnu stranicu, prikazano korisničko ime u navigaciji |
| 5 | Pokušaj registracije s istim emailom | Greška: "Email is already taken" |

---

## T-02 — Prijava i odjava

**Uloga**: registrovani korisnik
**Stranica**: `/login`

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | Otvori `/login` | Formular za prijavu |
| 2 | Unesi pogrešnu lozinku 3× | Greška "Invalid credentials" svaki put |
| 3 | Unesi ispravne podatke za `seeker@test.com` | Preusmjeren na `/` |
| 4 | U navigaciji klikni **Profil → Odjava** | Preusmjeren na `/login`, session cookie obrisan |
| 5 | Pokušaj otvoriti `/settings/security` direktno u URL baru | Preusmjeren na `/login` |

---

## T-03 — Prijava s MFA

**Uloga**: korisnik s uključenim MFA
**Preduvjet**: `admin@test.com` ima uključen MFA

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | Prijavi se s `admin@test.com` | Prikazan MFA challenge ekran (ne homepage) |
| 2 | Unesi pogrešan TOTP kod | Greška "Invalid MFA code" |
| 3 | Unesi ispravan TOTP kod iz autentikator aplikacije | Preusmjeren na homepage |
| 4 | Odjava i ponovna prijava s iste uređaja (isti browser) | Ako je uređaj označen kao pouzdan — direktna prijava bez MFA |
| 5 | Ponovi s incognito prozora | MFA challenge se prikaže ponovo |

---

## T-04 — Postavljanje MFA

**Uloga**: seeker (bez MFA)
**Stranica**: `/settings/security`

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | Prijavi se kao `seeker@test.com`, otvori `/settings/security` | Prikazan status MFA: **Isključen** |
| 2 | Klikni **Uključi MFA** | Prikazan QR kod i tajni ključ |
| 3 | Skeniraj QR kod u Google Authenticator, unesi generisani kod | Potvrda "MFA je uključen", prikazani recovery kodovi |
| 4 | Kopiraj/pohrani recovery kodove, klikni **Gotovo** | MFA status: **Uključen** |
| 5 | Odjava i ponovna prijava | MFA challenge se pojavi |
| 6 | Na MFA challenge ekranu klikni **Koristi recovery kod** | Polje za recovery kod |
| 7 | Unesi jedan od recovery kodova | Uspješna prijava; iskorišten kod više ne radi |

---

## T-05 — Upravljanje sesijama

**Uloga**: seeker
**Stranica**: `/settings/security`

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | Prijavi se u dva različita browser prozora | — |
| 2 | U prozoru 1 otvori `/settings/security` → kartica **Aktivne sesije** | Prikazane 2 sesije |
| 3 | Klikni **Odjavi** pored sesije prozora 2 | Sesija uklonjena iz liste |
| 4 | U prozoru 2 pokuša napraviti bilo kakav API poziv | Preusmjeren na `/login` |
| 5 | U prozoru 1 klikni **Odjavi sa svih ostalih uređaja** | Samo trenutna sesija ostaje u listi |

---

## T-06 — Pretraga nekretnina

**Uloga**: anonimni posjetitelj ili prijavljeni korisnik
**Stranica**: `/search`

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | Otvori `/search` | Prikazana lista nekretnina bez filtera |
| 2 | U polje za pretragu upiši grad (npr. "Sarajevo"), pritisni Enter | Lista se filtrira po lokaciji |
| 3 | Postavi filter **Cijena do: 500 EUR**, klikni **Primijeni** | Prikazane samo nekretnine ≤ 500 EUR |
| 4 | Klikni na kartu (tab **Karta**) | Prebaci se na prikaz mape s markerima |
| 5 | Klikni na marker na mapi | Pop-up s osnovnim podacima nekretnine |
| 6 | Klikni na naziv nekretnine u pop-upu | Otvara se detaljna stranica nekretnine |
| 7 | Vrati se nazad, klikni **Sačuvaj pretragu** | Pretraga sačuvana (prijava potrebna ako nije ulogovan) |

---

## T-07 — Detalji nekretnine

**Uloga**: seeker
**Stranica**: `/listing/:id`

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | Otvori neku nekretninu iz pretrage | Prikazana slika, opis, lokacija, cijena, ameniteti |
| 2 | Klikni na tab **Recenzije** | Lista recenzija ili poruka "Nema recenzija" |
| 3 | Klikni na tab **Ameniteti** | Lista ameniteta i oznaka prisutnosti |
| 4 | Klikni **Dodaj u omiljene** (srce) | Srce postaje aktivno, nekretnina dodana u favorite |
| 5 | Otvori `/favorites` | Nekretnina se pojavljuje u listi |
| 6 | Klikni **Kontaktiraj vlasnika** | Otvara chat prozor / `/chat/:id` |
| 7 | Klikni **Zakaži razgledanje** | Otvara se lista dostupnih termina |

---

## T-08 — Prijava za najam (Application)

**Uloga**: seeker
**Stranica**: `/listing/:id`

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | Otvori nekretninu, klikni **Apliciraj** | Formular za prijavu |
| 2 | Popuni formular (poruka, planirani datum useljenja) i klikni **Pošalji** | Potvrda "Prijava je poslana" |
| 3 | Otvori `/applications` | Prijava prikazana sa statusom **Na čekanju** |
| 4 | Otvori `/landlord/applications` kao `landlord@test.com` | Vidljiva nova prijava |
| 5 | Klikni **Prihvati** na prijavi | Status se mijenja u **Prihvaćena** |
| 6 | Klikni **Odbij** na drugoj prijavi | Status se mijenja u **Odbijena** |
| 7 | Seeker otvori `/applications` | Vidljive promjene statusa |

---

## T-09 — Chat / Poruke

**Uloga**: seeker + landlord
**Stranica**: `/chat/:id`

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | Seeker otvori chat prozor s landlord-om za nekretninu | Chat učitan, prikazana historija poruka |
| 2 | Seeker napiše poruku, pritisni Enter ili klikni Pošalji | Poruka vidljiva u chatu odmah |
| 3 | Landlord otvori `/messages` | Prikazan unread badge, nova poruka vidljiva |
| 4 | Landlord odgovori | Seeker vidi odgovor (bez reload-a ako WebSocket radi) |
| 5 | Seeker klikni na paper-clip ikonu, odaberi sliku | Slika priložena i prikazana u chatu |
| 6 | Klikni na priloženu sliku u chatu | Slika se otvori (inline prikaz ili download) |
| 7 | Klikni **Prijavite poruku** (report) | Formular za prijavu; klikni Pošalji | Potvrda prijave |

---

## T-10 — Zakazivanje razgledanja

**Uloga**: landlord + seeker
**Stranica**: `/listing/:id` i `/bookings`

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | Landlord otvori svoju nekretninu, klikni **Upravljaj terminima** | Prikaz rasporeda termina |
| 2 | Klikni **Dodaj termin**, unesi datum i sat, klikni **Spremi** | Novi termin dodan u listu |
| 3 | Seeker otvori istu nekretninu, klikni **Zakaži razgledanje** | Prikazani dostupni termini |
| 4 | Seeker odabere termin, klikni **Zatraži** | Poruka "Zahtjev poslan" |
| 5 | Landlord otvori `/bookings` tab **Razgledanja** | Vidljiv zahtjev sa statusom Na čekanju |
| 6 | Landlord klikni **Potvrdi** | Termin je potvrđen; seeker vidi status Potvrđen |
| 7 | Seeker klikni **Preuzmi .ics** | Preuzima se kalendarski fajl |
| 8 | Landlord klikni **Otkaži** na drugom zahtjevu | Status: Otkazano; seeker notificiran |

---

## T-11 — Kreiranje nekretnine (Landlord)

**Uloga**: landlord
**Stranica**: `/landlord/listings`

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | Klikni **Nova nekretnina** | Otvori formular za kreiranje |
| 2 | Popuni naziv, opis, tip, površinu, broj soba, cijenu | — |
| 3 | Unesi adresu | Geocoder pretraži i predloži koordinate |
| 4 | Dodaj slike (barem 1) | Thumbnailovi prikazani |
| 5 | Klikni **Spremi kao nacrt** | Nekretnina kreirana sa statusom Draft |
| 6 | Na listi nekretnina klikni **Objavi** | Status se mijenja u Active |
| 7 | Provjeri da je nekretnina vidljiva na `/search` | Pojavljuje se u rezultatima |
| 8 | Klikni **Ukloni iz objave** | Status: Unpublished, nevidljiva u pretrazi |
| 9 | Klikni **Arhiviraj** | Status: Archived |

---

## T-12 — Transakcija i ugovor

**Uloga**: landlord + seeker
**Stranica**: `/transactions`

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | Landlord stvori transakciju (ili prihvati aplikaciju) | Nova transakcija vidljiva na `/transactions` |
| 2 | Landlord otvori transakciju, klikni **Generiši ugovor** | Ugovor kreiran s podacima |
| 3 | Landlord klikni **Potpiši ugovor** | Ugovor označen kao potpisal landlord |
| 4 | Seeker otvori `/transactions`, klikni na istu transakciju | Vidi ugovor čeka potpis |
| 5 | Seeker klikni **Potpiši** | Ugovor potpisan od obje strane |
| 6 | Klikni **Preuzmi PDF** | PDF ugovora se preuzima |
| 7 | Klikni **Plati depozit (kartica)** | Preusmjeren na Stripe Checkout |
| 8 | Unesi test karticu `4242 4242 4242 4242`, datum u budućnosti, CVC 123 | Depozit plaćen, status ažuriran |

---

## T-13 — Recenzije i ocjene

**Uloga**: seeker (koji je imao transakciju s landlord-om)
**Stranica**: `/listing/:id/reviews` i `/user/:id`

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | Otvori nekretninu za koju ima završenu transakciju | Prikazan gumb **Ostavi recenziju** |
| 2 | Klikni **Ostavi recenziju**, unesi ocjenu (1–5 zvjezdica) i tekst | — |
| 3 | Klikni **Objavi** | Recenzija prikazana na stranici nekretnine |
| 4 | Landlord otvori svoju nekretninu | Vidi novu recenziju, može odgovoriti |
| 5 | Landlord klikni **Odgovori** | Odgovor prikazan ispod recenzije |
| 6 | Seeker klikni **Prijavi recenziju** | Formular za prijavu |
| 7 | Unesi razlog i klikni **Pošalji** | Potvrda prijave |

---

## T-14 — KYC verifikacija

**Uloga**: landlord
**Stranica**: `/profile/verification`

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | Otvori `/profile/verification` | Status: **Nije verifikovan** |
| 2 | Klikni **Pokreni verifikaciju** | Formular za upload dokumenata |
| 3 | Uploaduj dokumente (prednja/stražnja strana ID, selfie, dokaz adrese) | Thumbnailovi prikazani |
| 4 | Klikni **Pošalji** | Status: **Na čekanju** |
| 5 | Kao admin otvori `/admin/kyc` | Prikazana nova prijava |
| 6 | Admin klikni na prijavu → klikni na dokument | Dokument se otvori (PDF/slika), audit log bilježi pristup |
| 7 | Admin klikni **Odobri** | Status landlord-a: **Verifikovan** |
| 8 | Landlord otvori `/profile/verification` | Prikazan badge ✓ Verifikovan |

---

## T-15 — Notifikacije

**Uloga**: seeker
**Stranica**: `/notifications`

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | Landlord prihvati seekerovu prijavu | U navigaciji se pojavi crveni badge s brojem |
| 2 | Seeker klikni na bell ikonu | Otvara se `/notifications` s novom notifikacijom |
| 3 | Klikni na notifikaciju | Preusmjeren na relevantan kontekst (npr. aplikacija) |
| 4 | Otvori **Podešavanja notifikacija** (`/settings/notifications`) | Lista tipova notifikacija s toggle prekidačima |
| 5 | Isključi notifikacije za **Aplikacije**, klikni **Spremi** | Toggle je OFF |
| 6 | Landlord ažurira status aplikacije | Seeker NE dobija notifikaciju |

---

## T-16 — Sačuvane pretrage

**Uloga**: seeker
**Stranica**: `/search` → `/saved-searches`

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | Na `/search` postavi filtere (grad, cijena, tip), klikni **Sačuvaj pretragu** | Pretraga sačuvana, unos naziva pretrage |
| 2 | Otvori `/saved-searches` | Nova sačuvana pretraga vidljiva |
| 3 | Klikni na sačuvanu pretragu | Otvara `/search` s primijenjenim filterima |
| 4 | Klikni **Uredi** | Može promijeniti naziv i filtere |
| 5 | Klikni **Obriši** | Pretraga uklonjena iz liste |

---

## T-17 — Profil i postavke

**Uloga**: seeker
**Stranica**: `/settings/*`

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | Otvori `/settings/profile` | Prikazano ime, avatar |
| 2 | Klikni na avatar, odaberi sliku (max 5MB, JPG/PNG) | Slika uploadana, thumbnail prikazan |
| 3 | Promijeni ime, klikni **Spremi** | Ime ažurirano u navigaciji |
| 4 | Otvori `/settings/personal` | Prikazani: datum rođenja, spol, adresa, zaposlenje, telefon |
| 5 | Unesi broj telefona u pogrešnom formatu | Greška validacije |
| 6 | Unesi ispravne podatke, klikni **Spremi** | Podaci sačuvani |
| 7 | Otvori `/settings/security`, sekcija **Lozinka** | Forma za promjenu lozinke |
| 8 | Unesi staru lozinku, novu i potvrdu, klikni **Sačuvaj** | "Lozinka promijenjena"; sve ostale sesije automatski odjavljene |

---

## T-18 — Brisanje naloga (GDPR)

**Uloga**: seeker
**Stranica**: `/settings/security` ili `/settings/personal`

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | Scroll do sekcije **Opasna zona** ili **Obriši nalog** | Prikazan gumb **Trajno obriši nalog** |
| 2 | Klikni gumb | Dialog za potvrdu s upozorenjem o nepovratnosti |
| 3 | Klikni **Otkaži** | Dialog se zatvori, nalog nije obrisan |
| 4 | Klikni gumb ponovo, unesi pogrešnu lozinku u dialog | Greška "Netačna lozinka" |
| 5 | Unesi ispravnu lozinku, potvrdi | Preusmjeren na `/login`; nalog anonimiziran |
| 6 | Pokušaj se prijaviti s tim emailom | Greška "Invalid credentials" |

---

## T-19 — Admin panel: Korisnici i moderacija

**Uloga**: admin
**Stranica**: `/admin/users`, `/admin/moderation`

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | Otvori `/admin/users` | Lista korisnika s pretragom i filterima |
| 2 | Klikni na nekog korisnika → tab **Sigurnost** | Prikazani fraud score, aktivne sesije, signali |
| 3 | Klikni **Opozovi sve sesije** | Sve sesije korisnika uklonite; on biva odjavljen |
| 4 | Klikni **Označi kao sumnjivog** | `is_suspicious` postaje true, sesije opozvane |
| 5 | Klikni **Poništi sumnju** | Fraud score i signali obrisani, `is_suspicious` = false |
| 6 | Otvori `/admin/moderation` | Lista prijavljenih sadržaja |
| 7 | Klikni na report | Detalji prijave s opcijama Riješi/Odbaci |
| 8 | Klikni **Odbaci** uz razlog | Status prijave: Odbačeno |
| 9 | Klikni **Riješi** + označi korisnika sumnjivim | Status: Riješeno; sesije korisnika opozvane |

---

## T-20 — Admin panel: KYC pregled

**Uloga**: admin
**Stranica**: `/admin/kyc`

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | Otvori `/admin/kyc` | Lista KYC prijava filtrirane po statusu |
| 2 | Klikni na prijavu na čekanju | Detalji s dokumentima |
| 3 | Klikni na dokument (ID prednja strana) | Dokument se otvori/preuzme; audit log bilježi admina koji je pregledao |
| 4 | Klikni **Odobri** s notom | Status: Odobren; landlord prima notifikaciju |
| 5 | Klikni **Odbij** na drugoj prijavi s razlogom | Status: Odbijen; landlord prima notifikaciju |
| 6 | Otvori tab **Audit log** | Prikazani redovi s pristupima dokumentima (ko, kada, koji dokument) |

---

## T-21 — Admin panel: Recenzije

**Uloga**: admin
**Stranica**: `/admin/ratings`

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | Otvori `/admin/ratings` | Lista recenzija s brojem prijava |
| 2 | Klikni na recenziju | Detalji recenzije |
| 3 | Klikni **Obriši recenziju** | Recenzija uklonjena s platforme; audit log bilježi akciju |
| 4 | Klikni **Označi autora sumnjivim** | Korisnikov `is_suspicious` = true |

---

## T-22 — Sigurnost: Brute force zaštita

**Uloga**: anonimni napadač
**Stranica**: `/login`

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | Unesi neispravnu lozinku za `seeker@test.com` 10× zaredom | Prvih 9: greška "Invalid credentials" |
| 2 | 10. pokušaj | Greška "Too many failed login attempts" (429) |
| 3 | Odmah pokušaj s ispravnom lozinkom | Isti 429 odgovor (lockout aktivan) |
| 4 | Sačekaj 15 minuta (ili manualno obriši cache ključ) | Prijava uspješna s ispravnom lozinkom |

---

## T-23 — Jezična podrška

**Uloga**: bilo koji korisnik
**Stranica**: `/settings/language`

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | Otvori `/settings/language` | Lista dostupnih jezika |
| 2 | Odaberi drugi jezik (npr. Bosanski/Hrvatski), klikni **Sačuvaj** | Sučelje prevedeno; oznaka language sačuvana |
| 3 | Osvježi stranicu | Odabrani jezik ostaje aktivan |

---

## T-24 — Push notifikacije (Web Push)

**Uloga**: seeker (desktop Chrome)
**Preduvjet**: `VITE_ENABLE_WEB_PUSH=true`, VAPID ključevi konfigurisani

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | Prijavi se kao seeker | Browser prikazuje zahtjev za push dozvolu |
| 2 | Klikni **Dozvoli** | Subscriptons registrovana; push aktivan |
| 3 | Landlord pošalje seekeru poruku | Desktop push notifikacija pojavi se izvan browsera |
| 4 | Klikni na notifikaciju | Browser se otvori na relevantnom chatu |
| 5 | Otvori `/settings/notifications`, toggle off **Web Push** | Subscriptions deregistrovana |

---

## T-25 — Oporavak: zaboravljena lozinka

*(Ako je flow implementiran; izostaviti ako ne postoji)*

| # | Akcija | Očekivani rezultat |
|---|---|---|
| 1 | Na `/login` klikni **Zaboravili ste lozinku?** | Formular za reset |
| 2 | Unesi email, klikni **Pošalji** | Potvrda "Email poslan" (ako email postoji) |
| 3 | Klikni link iz emaila | Formular za novu lozinku |
| 4 | Unesi novu lozinku, klikni **Sačuvaj** | "Lozinka promijenjena", preusmjeren na login |

---

## Matrica pokrivenosti

| Komponenta | Testovi |
|---|---|
| Autentikacija i sesije | T-02, T-03, T-04, T-05, T-22 |
| Registracija | T-01 |
| MFA | T-03, T-04 |
| Pretraga i mapa | T-06 |
| Detalji nekretnine | T-07 |
| Kreiranje nekretnine | T-11 |
| Aplikacije | T-08 |
| Chat i poruke | T-09 |
| Razgledanja (viewings) | T-10 |
| Transakcije i ugovori | T-12 |
| Recenzije i ocjene | T-13 |
| KYC verifikacija | T-14, T-20 |
| Notifikacije | T-15 |
| Sačuvane pretrage | T-16 |
| Profil i postavke | T-17 |
| Brisanje naloga (GDPR) | T-18 |
| Admin: korisnici | T-19 |
| Admin: moderacija | T-19 |
| Admin: KYC | T-20 |
| Admin: recenzije | T-21 |
| Push notifikacije | T-24 |
| Jezična podrška | T-23 |
| Oporavak lozinke | T-25 |
