# Full Product Guide (End-User Friendly)

This document explains all app features in simple language, grouped by user role.

## 1) What This App Is
IzdajIznajmi connects tenants (seekers) and landlords.

You can:
- find rental listings
- send inquiries and applications
- chat with the other side
- complete verification steps
- sign rental contracts and handle deposit payments

## 2) User Roles
The app has 4 main roles:
- Guest (not logged in)
- Seeker/Tenant
- Landlord
- Admin

## 3) Features Available to Everyone (where applicable)

### Account and login
- Register account
- Login/logout
- Session-based secure login

### Profile settings
- Update personal profile data
- Change password
- Manage account verification status
- View public profile information where applicable

### Notifications
- In-app notifications (inside the app)
- Mark notifications as read
- Notification preferences by type
- Daily/weekly digest options
- Browser push notifications (supported browsers/devices)
- Manage push by device (enable/disable specific device endpoint)

### Security
- Optional MFA (Authenticator app / TOTP)
- Recovery codes for MFA
- Trusted devices
- Session/device history and session revocation

### Language/UI
- Multi-language text support (EN/SR)
- Responsive web UI for desktop/mobile

## 4) Guest Features (No Login)
- Open Home and browse public listing cards
- Use Search and filter listings
- Use search suggestions (city/query/amenity hints)
- Open listing details (images, facilities, description, location, reviews)
- Open Map view and inspect listing positions
- Open full facilities list and full reviews list pages
- Navigate app pages that do not require authentication

Guest restrictions:
- Cannot send inquiries/applications
- Cannot access chat
- Cannot manage favorites/saved searches
- Cannot access landlord/admin features

## 5) Seeker/Tenant Features

### 5.1 Browse and discovery
- Browse active listings
- Search by city/location text
- Filter by:
  - price range
  - category
  - rooms/area/guests
  - rating
  - instant booking
  - facilities/amenities
- Use map-focused discovery mode
- Use search autosuggest to speed up filtering
- Open full listing detail pages

### 5.2 Recommendations
- See personalized recommendations based on your activity
- Open similar listings from a listing detail
- Understand simple “why this listing” signals (for example: same city, similar price)

### 5.3 Favorites
- Add/remove listings to favorites
- Open dedicated favorites list

### 5.4 Saved searches and alerts
- Save search filters for later reuse
- Receive notification when new listings match your saved filters
- Open deep-link from notification back to filtered search

### 5.5 Inquiries and applications
- Send booking/inquiry request to landlord
- Add message, dates, guest count
- Track request status:
  - pending
  - accepted
  - rejected
  - cancelled
- Apply to listings where the flow is enabled
- View your own application/request list

### 5.6 Messaging (chat)
- Open conversation list
- Enter a listing-related conversation
- Send text messages
- Send attachments (image/PDF)
- See upload progress for attachments
- See typing indicator
- See online presence badge

### 5.7 Ratings and trust
- Rate landlord/listing after required conditions are met
- Report inappropriate ratings/content
- Report problematic listing-related content to moderation queue

### 5.8 Verification and KYC
- Request email verification code
- Confirm verification code
- Submit KYC documents (where required)
- Check KYC status:
  - pending
  - approved
  - rejected
  - withdrawn

### 5.9 Transaction participation
When a landlord starts a rental transaction, tenant can:
- review generated contract
- e-sign contract
- pay deposit via Stripe checkout
- track transaction status updates

## 6) Landlord Features

### 6.1 Listing management
- Open “My Listings”
- Create listing
- Edit listing fields (title, price, address, category, description, beds/baths, etc.)
- Upload listing images
- Manage listing facilities
- Set cover image and image order
- Set/adjust map pin location
- Reset location to automatic geocoding

### 6.2 Listing lifecycle and status
- Save draft
- Publish/activate listing
- Use instant-book option when creating/updating listing
- Pause listing
- Archive and restore listing
- Mark listing as rented
- Automatic expiration for old active listings

### 6.3 Incoming demand management
- View incoming inquiries/requests
- Accept/reject requests
- View seeker applications linked to listings
- Keep communication in listing-scoped threads

### 6.4 Messaging
- Full chat features with seekers:
  - text
  - attachments
  - typing indicator
  - online status

### 6.5 Verification and trust
- Submit KYC verification documents
- Track verification state
- Become verified landlord after approval
- Eligible for “top landlord” badge based on metrics (or admin override)

### 6.6 Transaction flow ownership
Landlord can:
- start transaction for listing + seeker
- generate rental contract PDF
- sign contract
- confirm move-in
- complete transaction lifecycle

## 7) Admin Features

### 7.1 Admin dashboard
- View KPI cards and trend summaries
- Monitor platform activity at high level

### 7.2 Moderation
- Review reports (ratings/messages/listings)
- Resolve/dismiss reports
- Remove or moderate problematic content where policy allows

### 7.3 KYC administration
- View pending KYC submissions
- Approve/reject KYC with notes
- Access KYC files through authorized private endpoints
- Keep audit trail of sensitive document access

### 7.4 User and security controls
- View user security summary
- View/revoke user sessions/devices
- Revoke all sessions when needed

### 7.5 Badge and trust controls
- Review landlord trust metrics
- Override badge state (example: top landlord)

### 7.6 Transaction administration
- Access admin transaction actions (for example payout/completion paths enabled in admin API)

### 7.7 Platform notices
- Send or manage admin-level notices/notifications (where configured)

### 7.8 Admin impersonation
- Temporarily impersonate user role for troubleshooting support issues
- Exit impersonation mode safely from UI

## 8) Notifications Explained (Simple)
The app can notify users in 3 ways:
- In-app notification center
- Notification badge/count updates
- Browser push notification (if enabled and supported)

Typical notification topics:
- new inquiry/application
- request status change
- new chat message
- rating-related events
- moderation updates
- daily/weekly digest summaries
- saved-search match alerts

## 9) Verification and Trust Model
- Email verification is active for account trust
- Phone verification flow is currently disabled
- KYC documents are private and never publicly exposed
- Only allowed users (owner/admin) can access protected verification files

## 10) Safety and Anti-Abuse Rules (User-visible impact)
- Chat message rate limits prevent spam
- Attachment upload limits apply (count, type, size)
- Seeker message anti-spam rule limits repeated one-sided messaging until landlord replies
- Authorization checks prevent access to other users’ protected data
- Duplicate notification prevention is implemented

## 11) Typical End-to-End Journeys

### Tenant journey
1. Register/login
2. Search listings and filter
3. Save favorites or saved searches
4. Send inquiry/application
5. Chat with landlord
6. Complete verification steps if required
7. Sign contract and pay deposit

### Landlord journey
1. Login
2. Create/publish listing
3. Receive inquiries/applications
4. Chat with seeker
5. Approve and start transaction
6. Generate/sign contract and confirm move-in

### Admin journey
1. Monitor KPIs
2. Moderate reports
3. Process KYC queue
4. Manage high-risk/security cases

## 12) Important Notes for Users
- Some features depend on role permissions.
- Some features (especially push notifications) depend on browser/device support.
- Production behavior may differ from local/demo mode in mock data and integrations.

## 13) Quick Glossary
- Listing: rental offer created by landlord.
- Inquiry/Request: tenant request sent to landlord.
- Application: formal apply action to a listing.
- KYC: identity/address verification process.
- Digest: grouped notifications (daily/weekly).
- Push notification: browser-level notification sent to your device.
