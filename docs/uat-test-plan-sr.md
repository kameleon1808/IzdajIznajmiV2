# UAT test plan (srpski) – IzdajIznajmi V2

## A) Uvod
Dokument vodi klijente, stakeholder-e i product owner-e kroz proveru prototipa (V2) u realnom UI-u. Test se radi klikom kroz aplikaciju (desktop ili telefon); backend postoji, ali sve akcije radimo iz ekrana, bez tehničkih koraka.

## B) Pre početka
- Najlakše: otvorite link aplikacije koji vam je poslao tim. Ako se testira lokalno, developer treba da pokrene aplikaciju i prosledi vam adresu (obično http://localhost:5173 za web deo).
- Ako se pojavi prazna stranica ili greška: osvežite (Refresh) i pokušajte ponovo.
- Test korisnici (email / lozinka `password`):
  - Admin: `admin@example.com`
  - Landlord: `lana@demo.com`, `leo@demo.com`
  - Tenant: `tena@demo.com`, `tomas@demo.com`, `tara@demo.com`

## C) Brza demonstracija (5–10 min)
1. Ulogujte se kao tenant `tena@demo.com` → ✅ ulazite na Home.
2. Home: vidite sekcije “Most Popular” i “Recommended” → klik na bilo koju karticu otvara detalj (slike, opis).
3. U detalju kliknite plavo dugme “Send Inquiry”, unesite poruku i datume → ✅ modal se zatvara, status “pending”.
4. Otvorite “My Booking” → tab “Requests” → ✅ vidite novi zahtev sa oznakom “pending”.
5. Otvorite “Messages”, izaberite razgovor, pošaljite poruku → ✅ poruka se pojavi u balonu.
6. Logout (Profile → Logout) → ✅ vraćeni ste na početni ekran za goste.

## D) Detaljni UAT scenariji

### 1) Guest pregled (bez prijave)
- Korisnik: Guest
- Koraci:
  1. Otvorite aplikaciju; na dnu su tabovi “Home”, “My Booking”, “Message”, “Profile”. ✅ Tabovi su vidljivi.
  2. Home: skrolujte “Most Popular” (vodoravne kartice) i “Recommended for you” (uspravne kartice). ✅ Kartice imaju slike, cenu, grad.
  3. Kliknite “Search” ikonicu u headeru ili otvorite tab “Search”. ✅ Prikazuje se traka za pretragu.
  4. U “Search” upišite pojam, otvorite filter (ikonica sa klizačima) → izaberite kategoriju/cenu/rejting, sačuvajte. ✅ Lista se osveži po izboru.
  5. Otvorite “Map”: vidite mapu (siva slika) i istaknutu karticu ispod. ✅ Kartica ima dugme “Booking Now” ili slično.
  6. Kliknite karticu oglasa → Listing Detail; pregledajte slike, “Common Facilities”, “Description”, “Location”, “Reviews”. ✅ Svaka sekcija je vidljiva.
  7. Kliknite “Facilities” (See all) i “Reviews” (View all) linkove. ✅ Otvaraju se posebne liste.
- Očekivano:
  - ✅ Kartice se učitavaju sa slikama i cenama.
  - ✅ Filteri menjaju listu (barem vizuelno).
  - ✅ Na detalju postoje slike/facilities/reviews (mogu biti placeholder).
- PASS/FAIL: FAIL ako linkovi ne rade, ako se ne učitaju liste ili ako se ne otvara detalj.
- Napomena: Mapa je statična slika (placeholder), online status u porukama je placeholder.

### 2) Tenant tok – favorites i inquiry
- Korisnik: Tenant (npr. `tena@demo.com`)
- Koraci:
  1. Login (email + password). ✅ Vraća na Home kao prijavljeni korisnik.
  2. Home → klik srce (heart) na kartici. ✅ Srce se ispuni/isprazni odmah.
  3. Otvorite “Favorites” (tab “My Favorite”): vidi se sačuvani oglas; klik na srce ponovo menja stanje. ✅ Lista se ažurira.
  4. Otvorite karticu oglasa → Listing Detail.
  5. Kliknite “Send Inquiry” (plavo dugme na dnu). U modalu unesite datume (ili ostavite prazno), broj gostiju, kratku poruku (“Planiramo dolazak…”). Klik “Send Request”. ✅ Modal se zatvara, poruka o uspehu.
  6. Otvorite “My Booking” → tab “Requests”: vidite zahtev sa badge-om (pending/accepted/rejected/cancelled). ✅ Status se prikazuje.
  7. Otvorite “Messages”: lista razgovora; uđite u chat, pošaljite poruku + attachujte sliku ili PDF. ✅ Nova poruka i attachment vidljivi u balonu.
  7a. Dok druga strana kuca poruku, proverite “is typing...” ispod headera. ✅ Typing indikator se pojavljuje.
  7b. Proverite online badge za drugog učesnika (ako je prisutan). ✅ “Online” se prikazuje kada je aktivan.
  8. Logout iz “Profile”. ✅ Vraća u gost režim.
- Očekivano:
  - ✅ Favoriti se ažuriraju odmah (srce menja boju).
  - ✅ Zahtev se pojavljuje u “Requests” sa status badge-om.
- ✅ Poruka i attachment se pojavljuju u chat bublu, istorija ostaje vidljiva.
- ✅ Typing indikator se pojavljuje pri kucanju druge strane.
- ✅ Online badge se prikazuje kada je druga strana aktivna.
- PASS/FAIL: FAIL ako se inquiry ne pojavi ili ako chat ne prikazuje novu poruku.

### 3) Landlord tok – listings i booking requests
- Korisnik: Landlord (npr. `lana@demo.com`)
- Koraci:
  1. Login. ✅ Otvara Home u ulozi landlord.
  2. “Profile” → “My Listings” → klik. ✅ Lista oglasa sa cenom/gradom i badge “Published”.
  3. Klik “+ New Listing”: otvara formu.
     - Unesite Title, Price per night, Category, Address, City, Country, Beds, Baths, opis (min 30 karaktera), 2–3 URL slike (copy/paste), opcioni “instant book”.
     - Klik “Save listing”. ✅ Vraća na listu, nova kartica se vidi.
  4. Klik na postojeću karticu → “Edit”: promenite Title ili cenu, sačuvajte. ✅ Kartica prikazuje novu vrednost.
  5. “My Booking” → tab “Requests”: lista dolaznih zahteva; izaberite “pending” → klik “Accept” ili “Reject”. ✅ Badge menja boju/tekst.
  6. “Messages”: otvorite listu, uđite u chat. ✅ Razgovor vidljiv, poruke prikazane.
  7. Logout. ✅ Povratak na gost režim.
- Očekivano:
  - ✅ Nova listing kartica se pojavi sa naslovom i slikom.
  - ✅ Izmena se vidi na kartici (novi naslov/cena).
  - ✅ Status zahteva se menja na badge (accepted/rejected).
- PASS/FAIL: FAIL ako se listing ne upiše/izmeni ili ako status zahteva ne promeni badge.
- Napomena: Upload je placeholder (URL unos), nema realnog fajl uploada.

### 4) Greške i prazna stanja
- Koraci:
  1. Otvorite “Messages” kao korisnik bez razgovora → očekuje se poruka “No conversations” (ili slično prazno stanje).
  2. Otvorite “Favorites” bez favorita → poruka “No favorites yet”.
  3. Otvorite “Search” i stavite ekstremne filtere → “No results” poruka.
  4. Pokušajte pristupiti “My Booking” kao Guest → očekuje se preusmerenje na Home + toast “Access denied”.
  5. Ako dobijete generalnu grešku (random fail), uradite Refresh i pokušajte ponovo; ako ostane, zabeležite URL i vreme za tim.
- Očekivano:
  - ✅ Jasne poruke praznih stanja.
  - ✅ Access denied blokira neovlašćenog korisnika.
  - ✅ Retry/refresh rešava povremene greške.
- PASS/FAIL: FAIL ako prazna stanja nisu prikazana ili ako se dozvoli pristup zabranjenim sekcijama.

## E) Završna checklist (mora da radi pre “OK”)
- ✅ Login/Logout za tenant i landlord.
- ✅ Home/Search/Map prikazuju liste i otvaraju detalje.
- ✅ Favorites srce menja stanje i vidi se u “My Favorite”.
- ✅ “Send Inquiry” kreira zahtev koji se vidi u “My Booking” → “Requests”.
- ✅ Landlord vidi “My Listings”, može kreirati i izmeniti oglas.
- ✅ Landlord može Accept/Reject pending request.
- ✅ Messages lista i chat prikazuju i šalju poruke i attachmente.
- ✅ Typing indikator i online badge rade u chatu.
- ✅ Prazna stanja i poruke o greškama se prikazuju umesto praznih ekrana.
