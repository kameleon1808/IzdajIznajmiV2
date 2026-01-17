# API Examples (cURL)

Uses Sanctum cookie/session auth. Keep a cookie jar and send the XSRF token header on state-changing requests.

```bash
# 1) Grab CSRF + session cookies
curl -c cookies.txt -X GET http://localhost:8000/sanctum/csrf-cookie
XSRF=$(grep XSRF-TOKEN cookies.txt | tail -n1 | awk '{print $7}')

# 2) Login (session cookie)
curl -b cookies.txt -c cookies.txt -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -H "X-XSRF-TOKEN: $XSRF" \
  -d '{"email":"tena@demo.com","password":"password"}'

# 3) Listings
curl -b cookies.txt http://localhost:8000/api/v1/listings?category=villa&priceMin=100&priceMax=300

# 4) Create listing with images (landlord/admin)
curl -b cookies.txt -c cookies.txt -X POST http://localhost:8000/api/v1/landlord/listings \
  -H "X-XSRF-TOKEN: $XSRF" \
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

# 5) Update listing images (keep + add)
curl -b cookies.txt -c cookies.txt -X POST "http://localhost:8000/api/v1/landlord/listings/1?_method=PUT" \
  -H "X-XSRF-TOKEN: $XSRF" \
  -F "keepImages=[{\"url\":\"http://localhost:8000/storage/listings/1/photo1.jpg\",\"sortOrder\":0,\"isCover\":true}]" \
  -F "images[]=@/path/to/new-photo.jpg"

# 6) Publish / Unpublish / Archive
curl -b cookies.txt -X PATCH http://localhost:8000/api/v1/landlord/listings/1/publish -H "X-XSRF-TOKEN: $XSRF"
curl -b cookies.txt -X PATCH http://localhost:8000/api/v1/landlord/listings/1/unpublish -H "X-XSRF-TOKEN: $XSRF"
curl -b cookies.txt -X PATCH http://localhost:8000/api/v1/landlord/listings/1/archive -H "X-XSRF-TOKEN: $XSRF"

# 7) Create Booking Request (seeker)
curl -b cookies.txt -c cookies.txt -X POST http://localhost:8000/api/v1/booking-requests \
  -H "X-XSRF-TOKEN: $XSRF" \
  -H "Content-Type: application/json" \
  -d '{"listingId":1,"landlordId":2,"guests":2,"message":"We would like to stay"}'

# 8) Accept Booking Request (landlord)
curl -b cookies.txt -c cookies.txt -X PATCH http://localhost:8000/api/v1/booking-requests/1 \
  -H "X-XSRF-TOKEN: $XSRF" \
  -H "Content-Type: application/json" \
  -d '{"status":"accepted"}'
```
