# API Examples (cURL)

## Auth
```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Tena","email":"tena@example.com","password":"password","password_confirmation":"password","role":"tenant"}'
```

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"tena@example.com","password":"password"}'
```

## Listings
```bash
curl http://localhost:8000/api/listings?category=villa&priceMin=100&priceMax=300
```

## Create Booking Request (Tenant)
```bash
curl -X POST http://localhost:8000/api/booking-requests \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"listingId":1,"landlordId":2,"guests":2,"message":"We would like to stay"}'
```

## Accept Booking Request (Landlord)
```bash
curl -X PATCH http://localhost:8000/api/booking-requests/1 \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"status":"accepted"}'
```
