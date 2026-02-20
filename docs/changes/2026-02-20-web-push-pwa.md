# Web Push + PWA Change Log - 2026-02-20

Ovaj dokument pokriva celu fazu uvodjenja Web Push notifikacija (desktop + Android), PWA service worker-a i production hardening koraka koji su uradjeni tokom implementacije.

## 1) Cilj i opseg

Cilj faze:
- Browser push notifikacije za postojece in-app notifikacije.
- Opt-in/opt-out flow po uredjaju.
- Backend queue delivery preko VAPID kljuceva.
- Bez regresije u postojecem notifications sistemu.

Nije pokriveno u ovoj fazi:
- Native iOS push (APNS native app tok).
- Poseban quiet-hours model (projekat trenutno nema poseban data model za quiet-hours u notifications preferencama).

## 2) Backend arhitektura (sta je uradjeno)

### 2.1 Novi data model
Dodate migracije:
- `backend/database/migrations/2026_02_20_000500_create_push_subscriptions_table.php`
- `backend/database/migrations/2026_02_20_000501_add_push_enabled_to_notification_preferences_table.php`

Tabela `push_subscriptions`:
- `user_id`, `endpoint` (unique), `p256dh`, `auth`, `user_agent`, `device_label`, `is_enabled`.

Tabela `notification_preferences`:
- dodat `push_enabled` (bool, default false).

### 2.2 API endpointi
Dodati endpointi pod `/api/v1`:
- `POST /push/subscribe`
- `POST /push/unsubscribe`
- `GET /push/subscriptions`

Implementacija:
- `backend/app/Http/Controllers/PushSubscriptionController.php`
- route wiring u `backend/routes/api.php`
- rate limiter `push_subscriptions` u `backend/app/Providers/AppServiceProvider.php`

Ponashanje:
- `subscribe` upsert-uje endpoint i ukljucuje `notification_preferences.push_enabled=true`.
- `unsubscribe` gasi konkretan endpoint (`is_enabled=false`), a ako nema vise aktivnih endpoint-a gasi `push_enabled`.

### 2.3 Dispatch i queue delivery
Postojeci tok notifikacija je prosiren:
- `backend/app/Jobs/DispatchNotificationJob.php` i dalje pravi in-app notifikaciju.
- Ako su uslovi ispunjeni (`push_enabled=true` + aktivna subscription), enqueue-uje:
  - `backend/app/Jobs/SendWebPushNotificationJob.php`

`SendWebPushNotificationJob`:
- koristi `minishlink/web-push` + VAPID.
- gradi payload (`title`, `body`, `icon`, `badge`, `url`, `data`).
- preskace digest tipove.
- na `404/410` automatski disable-uje endpoint.
- kada VAPID nije konfigurisan loguje `push_vapid_not_configured` i izlazi bez fail-a.

### 2.4 Config i env
Dodato:
- `backend/config/push.php`
- `backend/.env.example`:
  - `VAPID_PUBLIC_KEY`
  - `VAPID_PRIVATE_KEY`
  - `VAPID_SUBJECT`
  - `PUSH_NOTIFICATION_ICON`
  - `PUSH_NOTIFICATION_BADGE`

## 3) Frontend arhitektura (sta je uradjeno)

### 3.1 Service Worker
Novi fajl:
- `frontend/public/sw.js`

Implementirano:
- `push` event -> prikaz browser notifikacije.
- `notificationclick` -> deep-link u SPA (`/notifications` fallback).

### 3.2 Push servis i subscription flow
Novi servis:
- `frontend/src/services/push.ts`

Funkcionalnosti:
- registracija SW (`/sw.js`)
- subscribe/unsubscribe browser endpoint-a
- mapiranje uredjaja iz backend API-a
- validacija/flow permission state-a

### 3.3 UI integracija
Izmene:
- `frontend/src/pages/SettingsNotifications.vue`
- `frontend/src/main.ts`
- `frontend/src/stores/notifications.ts`
- `frontend/src/stores/language.ts`

Sta je dodato u UI:
- prikaz permission state-a
- enable/disable push dugmad
- lista aktivnih uredjaja
- disable pojedinacnog uredjaja

### 3.4 Frontend env
- `frontend/.env.example`:
  - `VITE_ENABLE_WEB_PUSH`
  - `VITE_VAPID_PUBLIC_KEY`

## 4) Production hardening (Docker/Nginx)

Tokom produkcionog testiranja uradjene su dve bitne stabilizacije.

### 4.1 Eksplicitan VAPID env u production compose-u
Izmena:
- `docker-compose.production.yml`

Dodate env varijable u `backend` i `queue` servise:
- `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT`, `PUSH_NOTIFICATION_ICON`, `PUSH_NOTIFICATION_BADGE`

Dodate env varijable u `frontend` servis:
- `VITE_ENABLE_WEB_PUSH`, `VITE_VAPID_PUBLIC_KEY`

Razlog:
- bez eksplicitnog mapiranja, `config('push.vapid')` je ostajao `null` u queue runtime-u i push se nije slao.

### 4.2 Nginx gateway DNS re-resolve fix
Izmena:
- `ops/nginx-docker-production.conf`

Dodato:
- `resolver 127.0.0.11 ...`
- `proxy_pass` preko promenljivih upstream adresa

Razlog:
- nakon restarta `backend/frontend` kontejnera, Nginx je drzao stare container IP adrese i vracao `502 Bad Gateway`.

## 5) Testovi i verifikacija

### 5.1 Backend automatizovani testovi
Dodato/izmenjeno:
- `backend/tests/Feature/PushSubscriptionsApiTest.php`
- `backend/tests/Feature/NotificationsApiTest.php`

Pokri ce:
- subscribe/unsubscribe/list API
- enqueue odluka za `SendWebPushNotificationJob`

### 5.2 Frontend verifikacija
- `npm run test` prolazi
- build prolazi (`npm run build`)

## 6) Operativni runbook (za buduce developere)

Koristi alias:
```bash
DC="docker compose -p izdaji_prod --env-file .env.production.compose -f docker-compose.production.yml"
```

### 6.1 Deploy / refresh nakon promene env/config
```bash
$DC up -d --build --force-recreate backend queue scheduler frontend
$DC exec backend php artisan optimize:clear
$DC exec backend php artisan config:cache
$DC restart backend queue scheduler gateway
```

### 6.2 Brza dijagnostika push-a
1. Da li su VAPID vrednosti vidljive queue runtime-u:
```bash
$DC exec queue php artisan tinker --execute="dump(config('push.vapid'));"
```

2. Da li se push job izvrsava:
```bash
$DC logs --tail=200 queue
```

3. Da li postoje push warning logovi:
```bash
$DC exec backend sh -lc "grep -R 'push_' storage/logs -n | tail -n 50"
```

4. Da li API upisuje subscription:
```bash
$DC logs --tail=200 gateway | grep '/api/v1/push/'
```

### 6.3 Kada korisnik ne dobija push
Proveri redom:
- browser permission = granted
- subscription postoji i `isEnabled=true`
- `notification_preferences.push_enabled=true`
- `config('push.vapid')` nije null
- nema `push_vapid_not_configured` u logu
- Brave: opcija `Use Google services for push messaging` ukljucena

### 6.4 Kada public tunnel vraca 502
- proveri gateway log:
```bash
$DC logs --tail=200 gateway
```
- ako je `connection refused` na stare upstream IP adrese, restart gateway:
```bash
$DC restart gateway
```
- nakon Nginx resolver fix-a ovo treba da bude retko i kratkotrajno.

## 7) Spisak kljucnih fajlova za odrzavanje

Backend:
- `backend/app/Jobs/DispatchNotificationJob.php`
- `backend/app/Jobs/SendWebPushNotificationJob.php`
- `backend/app/Http/Controllers/PushSubscriptionController.php`
- `backend/config/push.php`
- `backend/database/migrations/2026_02_20_000500_create_push_subscriptions_table.php`
- `backend/database/migrations/2026_02_20_000501_add_push_enabled_to_notification_preferences_table.php`

Frontend:
- `frontend/public/sw.js`
- `frontend/src/services/push.ts`
- `frontend/src/pages/SettingsNotifications.vue`

Infra/Ops:
- `docker-compose.production.yml`
- `ops/nginx-docker-production.conf`

## 8) Napomena o lokalnim env fajlovima
`frontend/.env` je lokalni runtime fajl i ne treba da bude verzionisan.
Dodat je u root `.gitignore` kao `frontend/.env`.
