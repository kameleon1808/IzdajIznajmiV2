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

## Watch (docker compose)
- `Watch` je opcija koja prati promene i automatski radi sync/rebuild.
- U ovom projektu nije neophodan zbog bind mount-ova (`backend/`, `frontend/`).
- Ako se pojavi meni: pritisni `d` za detach ili ignorisi.
