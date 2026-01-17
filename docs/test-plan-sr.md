# Test plan (srpski) – IzdajIznajmiV2

## A) Priprema okruženja / Setup
- Backend:
  ```bash
  cd backend
  composer install
  cp .env.example .env
  php artisan key:generate
  php artisan migrate:fresh --seed
  php artisan serve --host=0.0.0.0 --port=8000
  ```
- Frontend:
  ```bash
  cd frontend
  npm install
  npm run dev -- --host --port 5173
  ```
- Base URL / CORS: API `http://localhost:8000/api/v1`, SPA `http://localhost:5173` (stateful cookies dozvoljene u CORS/Sanctum).
- Demo korisnici (lozinka svima `password`):
  - admin@example.com (admin)
  - lana@demo.com, leo@demo.com (landlords)
  - tena@demo.com, tomas@demo.com, tara@demo.com (seekers)
- Dokumentacija: `docs/api-contract.md`, `docs/api-examples.md`, `docs/ui.md`

## B) Manualni test slučajevi

### Auth & role pristup
| ID | Precondition | Koraci | Očekivano | Napomena |
| --- | --- | --- | --- | --- |
| AUTH-01 | Backend radi | 1) GET /sanctum/csrf-cookie 2) POST /api/v1/auth/register sa novim emailom | 201, session cookie + user.role default seeker | register |
| AUTH-02 | AUTH-01 session | 1) GET /api/v1/auth/me sa session cookie | 200, vraća user sa rolom | me |
| AUTH-03 | Seeker session | 1) POST /api/v1/landlord/listings (seeker) | 403 Forbidden | policy |
| AUTH-04 | Bez session | 1) GET /api/v1/landlord/listings | 401 | auth guard |

### Listing browse/filter/detail
| ID | Precondition | Koraci | Očekivano | Napomena |
| --- | --- | --- | --- | --- |
| LST-01 | Seed podaci | 1) GET /api/v1/listings | 200, lista >0, camelCase polja | list |
| LST-02 | Seed podaci | 1) GET /api/v1/listings?category=villa&priceMin=100&priceMax=300&rating=4.5 | 200, svi rezultati po filteru | filter |
| LST-03 | Seed podaci | 1) GET /api/v1/listings/{id} | 200, uključuje images[], facilities[] | detail |

### Landlord listings CRUD & policy
| ID | Precondition | Koraci | Očekivano | Napomena |
| --- | --- | --- | --- | --- |
| LL-01 | Landlord token | 1) POST /api/v1/landlord/listings sa valid payloadom (title, pricePerNight, category, city, country, address, beds, baths, images[]) | 201, kreirana listing sa coverImage = images[0] | create |
| LL-02 | LL-01 listing | 1) PUT /api/v1/landlord/listings/{id} sa promenom title | 200, title ažuriran | update |
| LL-03 | Landlord B token, listing A vlasništvo | 1) PUT /api/v1/landlord/listings/{A-id} | 403 | policy |
| LL-04 | Landlord token | 1) GET /api/v1/landlord/listings | 200, samo njegove + facilities/images | owner filter |

### Booking requests (inquiry)
| ID | Precondition | Koraci | Očekivano | Napomena |
| --- | --- | --- | --- | --- |
| BR-01 | Seeker session, listing owner=landlord | 1) POST /api/v1/booking-requests (listingId, landlordId, guests, message) | 201, status pending | create |
| BR-02 | Seeker session | 1) GET /api/v1/booking-requests?role=seeker | 200, samo sopstveni | seeker view |
| BR-03 | Landlord token | 1) GET /api/v1/booking-requests?role=landlord | 200, incoming | landlord view |
| BR-04 | Landlord token | 1) PATCH /api/v1/booking-requests/{id} status=accepted | 200, status accepted | accept |
| BR-05 | Tenant token, status pending | 1) PATCH /api/v1/booking-requests/{id} status=cancelled | 200, status cancelled | cancel |
| BR-06 | Tenant token, pokuša accepted | 1) PATCH status=accepted | 403 | policy |
| BR-07 | Admin token | 1) PATCH bilo koji request status=rejected | 200 | admin override |

### Messaging skeleton
| ID | Precondition | Koraci | Očekivano | Napomena |
| --- | --- | --- | --- | --- |
| MSG-01 | Seeker session | 1) GET /api/v1/conversations | 200, lista gde seeker učestvuje | conv list |
| MSG-02 | Participant token | 1) GET /api/v1/conversations/{id}/messages | 200, <=50 msg, sorted asc | messages |
| MSG-03 | Non-participant token | 1) GET /api/v1/conversations/{id}/messages | 403 | authz |

### Frontend sanitarni testovi
| ID | Precondition | Koraci | Očekivano | Napomena |
| --- | --- | --- | --- | --- |
| FE-01 | Frontend dev server, mock store | 1) Uloguj se (mock role switch) 2) Navigacija na /favorites kao guest | Redirect na /, toast “Access denied” | guard |
| FE-02 | Role switch landlord | 1) /profile -> switch na Landlord 2) proveri da link „My Listings” vodi na /landlord/listings | Radi, prikazuje listing kartice | nav |
| FE-03 | Loading/Empty/Error | 1) Simuliraj slow network 2) Otvori /search i /map 3) Proveri skeleton/empty/error bannere/toasts | UI stanja prikazana | UX |

## C) API cURL primeri
> Base: `http://localhost:8000`

Koristite Sanctum cookie/session flow: prvo `GET /sanctum/csrf-cookie`, zatim POST/GET rute sa `--cookie-jar` i `X-XSRF-TOKEN` header-om (vidi `docs/api-examples.md` za kompletne primere).

## D) Negativni testovi (401/403/422/404)
- 401: GET /api/v1/landlord/listings bez session → 401 JSON `{message:"Unauthenticated."}`
- 403: Seeker PATCH /api/v1/booking-requests/{id} status=accepted → 403
- 403: Landlord B PUT /api/v1/landlord/listings/{listingA} → 403
- 422: POST /api/v1/booking-requests bez landlordId ili message<5 → 422 sa validation errors
- 422: POST /api/v1/landlord/listings sa category=“cabin” → 422
- 404: GET /api/v1/listings/99999 → 404

## E) Smoke checklist (≈10 min)
1) POST /api/v1/auth/login (tenant) → token dobijen
2) GET /api/v1/listings → 200, data array
3) GET /api/v1/listings/{id} → 200, ima images/facilities
4) POST /api/v1/booking-requests (tenant) → 201 pending
5) GET /api/v1/booking-requests?role=tenant → sadrži novi request
6) PATCH /api/v1/booking-requests/{id} (landlord) status=accepted → 200
7) GET /api/v1/landlord/listings (landlord) → 200, samo njegove
8) PUT /api/v1/landlord/listings/{id} (vlasnik) → 200
9) GET /api/v1/conversations (tenant) → 200 lista
10) Frontend: otvori Home/Search → vidi skeleton/karte; switch role u Profile radi i guard blokira nepristupačne rute
