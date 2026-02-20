# Chat & Realtime Support Runbook

Dokument za support i dalji razvoj chat/realtime funkcionalnosti.

## Scope
- Chat poruke i thread pravila.
- Chat attachment-i (image/pdf, private storage).
- Typing indikator i online presence (polling).
- In-app notification badge/dropdown realtime osvezavanje (polling).

## Arhitektura (trenutno stanje)
- Realtime je polling-based (nema obaveznih WebSocket zavisnosti za chat i notification UI).
- Backend ostaje source of truth; frontend periodicno osvezava stanje.
- Chat attachment originali su privatni i dostupni samo participant-ima konverzacije.

## Backend: kljucne tacke
- Chat poruke:
  - `POST /api/v1/conversations/{conversation}/messages`
  - `GET /api/v1/conversations/{conversation}/messages`
- Typing/presence:
  - `POST /api/v1/conversations/{conversation}/typing`
  - `GET /api/v1/conversations/{conversation}/typing`
  - `POST /api/v1/presence/ping`
  - `GET /api/v1/users/{user}/presence`
- Attachment-i:
  - `GET /api/v1/chat/attachments/{attachment}`
  - `GET /api/v1/chat/attachments/{attachment}/thumb`
- Rate limits:
  - `chat_messages`: 30/min po user/thread
  - `chat_attachments`: 10/10min po user/thread

## Frontend: polling intervali
- Chat thread poruke:
  - `frontend/src/pages/Chat.vue`
  - Polling poruka na ~3s kada je thread otvoren.
- Typing:
  - Polling statusa na ~4s.
  - Slanje `is_typing=true/false` pri kucanju/stop/blur/send.
- Presence:
  - Ping na ~25s.
  - Provera online statusa druge strane na ~30s.
- Notifications:
  - `frontend/src/components/notifications/NotificationBell.vue`
  - Polling `unread-count` na ~15s.
  - Refresh na tab focus/visibilitychange.
  - Otvaranje dropdown-a radi fresh fetch liste.

## Notifikacije: anti-duplication
- Event auto-discovery je eksplicitno iskljucen:
  - `backend/bootstrap/app.php` -> `withEvents(discover: false)`
  - `backend/app/Providers/EventServiceProvider.php` -> `shouldDiscoverEvents(): false`
- Razlog: bez ovoga isti listener moze biti registrovan 2x (`Class` + `Class@handle`) i kreirati duple notifikacije.

## Operativne komande (diagnostics)
- Provera event registracija:
```bash
cd backend
php artisan event:clear
php artisan event:list
```
- Ocekivanje: za `App\Events\MessageCreated` postoji jedan app listener (`SendMessageNotification`), ne duplikat.
- Ciscenje app cache-a posle deploy-a:
```bash
php artisan optimize:clear
```

## Brzi troubleshooting
1. Chat ne osvezava bez refresh-a:
- Proveri da li frontend salje periodicni `GET /api/v1/conversations/{id}/messages`.
- Proveri da li nema aktivne WebSocket-only zavisnosti za taj ekran.

2. Typing/online ne rade:
- Proveri `POST/GET typing` i `POST ping + GET presence` pozive u Network tabu.
- Proveri cache backend-a (TTL kljucevi `typing:*` i `presence:*`).

3. Notification badge kasni:
- Proveri `GET /api/v1/notifications/unread-count` periodicno (15s + focus refresh).
- Proveri da `fetchNotifications` ne prepisuje `unreadCount` iz parcijalnog page payload-a.

4. Dvostruke notifikacije:
- Pokreni `php artisan event:list`.
- Ako se vidi `SendMessageNotification` i `SendMessageNotification@handle` zajedno, event discovery nije pravilno ugasen ili je stale cache.
- Pokreni `php artisan event:clear` i `php artisan optimize:clear`, pa redeploy/restart backend procesa.

## Realtime QA (manual)
1. Otvori isti chat sa 2 korisnika u 2 taba/browsera.
2. Posalji poruku sa A -> proveri da se kod B pojavi bez refresh-a.
3. Kucaj u A -> proveri typing indikator kod B.
4. Odrzi oba korisnika aktivnim -> proveri online badge.
5. Posalji novu poruku -> proveri da notification badge poraste bez refresh-a.
6. Potvrdi da je broj notifikacija 1 po poruci (bez dupliranja).

## Relevantni fajlovi
- Backend:
  - `backend/app/Http/Controllers/ConversationController.php`
  - `backend/app/Http/Controllers/ChatSignalController.php`
  - `backend/app/Listeners/SendMessageNotification.php`
  - `backend/app/Providers/EventServiceProvider.php`
  - `backend/bootstrap/app.php`
- Frontend:
  - `frontend/src/pages/Chat.vue`
  - `frontend/src/components/notifications/NotificationBell.vue`
  - `frontend/src/stores/notifications.ts`
  - `frontend/src/stores/chat.ts`
