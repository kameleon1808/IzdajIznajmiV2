# Docker manual (projekat: IzdajIznajmiV2)

Kratak podsetnik za pokretanje, kontrolu i troubleshooting Docker okruzenja.

## Osnovno pokretanje
- Start (prvi put ili posle izmena image-a):
```bash
docker compose up --build
```
- Start u pozadini (detach):
```bash
docker compose up -d --build
```
- Stop + ukloni kontejnere:
```bash
docker compose down
```

## Status i logovi
- Status servisa (u root folderu repo-a):
```bash
docker compose ps
```
- Svi aktivni kontejneri:
```bash
docker ps
```
- Svi kontejneri (ukljucujuci zaustavljene):
```bash
docker ps -a
```
- Logovi:
```bash
docker compose logs
```
- Logovi za konkretan servis (npr. backend):
```bash
docker compose logs --tail=200 backend
```
- Pracenje logova uzivo:
```bash
docker compose logs -f
```

## Ulazak u kontejnere
- Shell u backend:
```bash
docker compose exec backend sh
```
- Jednokratna komanda u backend:
```bash
docker compose exec -T backend php artisan migrate:fresh --seed
```

## Inicijalne komande (backend)
- Migracije + seed:
```bash
docker compose run --rm backend php artisan migrate:fresh --seed
```
- Reindex (MeiliSearch):
```bash
docker compose run --rm backend php artisan search:listings:reindex --reset
```

## Restart / oporavak pojedinacnih servisa
- Restart servisa:
```bash
docker compose restart queue scheduler reverb
```
- Pokretanje samo odabranih servisa:
```bash
docker compose up -d queue scheduler reverb
```

## Ciscenje
- Stop + ukloni kontejnere i mreze:
```bash
docker compose down
```
- Ukloni i volume-e (brisanje DB/storage/node_modules):
```bash
docker compose down -v
```

## Portovi
- API: `http://localhost:8000`
- Frontend: `http://localhost:5173`
- Meili: `http://localhost:7700`



## Production compose (odvojeno od lokalnog razvoja)
- Production fajl: `docker-compose.production.yml`
- Env template: `.env.production.compose.example`
- Faza Web Push + PWA (detalji implementacije + troubleshooting): `docs/changes/2026-02-20-web-push-pwa.md`
- Priprema env:
```bash
cp .env.production.compose.example .env.production.compose
```
- Start production stack:
```bash
docker compose -p izdaji_prod --env-file .env.production.compose -f docker-compose.production.yml up -d --build
```
- Inicijalne migracije:
```bash
docker compose -p izdaji_prod --env-file .env.production.compose -f docker-compose.production.yml exec backend php artisan migrate:fresh --seed
```
- Provera health endpoint-a (preko gateway-a):
```bash
curl -f http://localhost/api/v1/health
```
- Javno izlaganje (Cloudflare Quick Tunnel, bez otvaranja portova na ruteru):
```bash
docker compose -p izdaji_prod --env-file .env.production.compose -f docker-compose.production.yml --profile public up -d tunnel
```
- Pracenje logova tunela (URL ce biti `https://...trycloudflare.com`):
```bash
docker compose -p izdaji_prod --env-file .env.production.compose -f docker-compose.production.yml logs -f tunnel
```
- Jednokratno citanje javnog URL-a:
```bash
docker compose -p izdaji_prod --env-file .env.production.compose -f docker-compose.production.yml logs tunnel | grep -oE 'https://[a-z0-9-]+\\.trycloudflare\\.com' | head -n 1
```
- Napomena: `trycloudflare` URL se obicno menja nakon restarta tunnel servisa.
- Stop production stack:
```bash
docker compose -p izdaji_prod --env-file .env.production.compose -f docker-compose.production.yml down
```

## VAZNO: kako se vide izmene
- Production test stack koristi bind mount za `./backend` i `./frontend`, pa izmene koje napravis lokalno ulaze i u running production test kontejnere.
- To znaci da izmene mogu biti vidljive i na `localhost` (dev) i na production test URL-u, jer dele isti source kod na disku.
- Ako hoces potpunu izolaciju, koristi odvojeni clone projekta ili image-only deployment bez bind mount-a.

## Kada treba dodatna akcija
- Backend `.php` izmene: najcesce su odmah vidljive (refresh stranice).
- Frontend izmene u production stack-u: potrebno je rebuild-ovati frontend servis (nema HMR kao u dev modu):
```bash
docker compose -p izdaji_prod --env-file .env.production.compose -f docker-compose.production.yml up -d --build frontend
```
- Alternativno (kada je frontend kontejner vec podignut), moze i:
```bash
docker compose -p izdaji_prod --env-file .env.production.compose -f docker-compose.production.yml exec frontend npm run build
```
- Kada menjas `.env`, `config/*`, rute ili middleware:
```bash
docker compose -p izdaji_prod --env-file .env.production.compose -f docker-compose.production.yml exec backend php artisan optimize:clear
docker compose -p izdaji_prod --env-file .env.production.compose -f docker-compose.production.yml exec backend php artisan event:clear
docker compose -p izdaji_prod --env-file .env.production.compose -f docker-compose.production.yml up -d --force-recreate backend queue scheduler reverb
```
- Kada menjas event/listener wiring (npr. notifikacije, chat events), proveri registracije:
```bash
docker compose -p izdaji_prod --env-file .env.production.compose -f docker-compose.production.yml exec backend php artisan event:list
```
- Kada menjas migracije:
```bash
docker compose -p izdaji_prod --env-file .env.production.compose -f docker-compose.production.yml exec backend php artisan migrate --force
```

## Watch (docker compose)
- `Watch` je opcija koja prati promene i automatski radi sync/rebuild.
- U ovom projektu nije neophodan zbog bind mount-ova (`backend/`, `frontend/`).
- Ako se pojavi meni: pritisni `d` za detach ili ignorisi.
