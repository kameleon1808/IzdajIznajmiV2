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

### Create listing with images (Landlord)
```bash
curl -X POST http://localhost:8000/api/landlord/listings \
  -H "Authorization: Bearer <TOKEN>" \
  -F "title=New Stay" \
  -F "pricePerNight=220" \
  -F "category=villa" \
  -F "city=Split" \
  -F "country=Croatia" \
  -F "address=Jadranska 12" \
  -F "beds=3" \
  -F "baths=2" \
  -F "description=Beautiful place by the sea with pool and terrace" \
  -F "facilities[]=Pool" \
  -F "facilities[]=Wi-Fi" \
  -F "images[]=@/path/to/photo1.jpg" \
  -F "images[]=@/path/to/photo2.jpg"
```

### Update listing images (keep + add)
```bash
curl -X POST http://localhost:8000/api/landlord/listings/1?_method=PUT \
  -H "Authorization: Bearer <TOKEN>" \
  -F "keepImageUrls[]=http://localhost:8000/storage/listings/1/photo1.jpg" \
  -F "images[]=@/path/to/new-photo.jpg"
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
