<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Rental Agreement</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 12px; line-height: 1.5; }
        h1 { font-size: 20px; margin-bottom: 6px; }
        h2 { font-size: 14px; margin-top: 18px; }
        .muted { color: #6b7280; }
        .section { margin-bottom: 12px; }
        .box { border: 1px solid #e5e7eb; padding: 10px; border-radius: 6px; }
        .row { display: flex; justify-content: space-between; }
        .col { width: 48%; }
    </style>
</head>
<body>
    <h1>Rental Agreement</h1>
    <p class="muted">Version {{ $version }} · Generated {{ $generated_at }}</p>

    <div class="section box">
        <div class="row">
            <div class="col">
                <strong>Landlord</strong>
                <div>{{ $landlord_name }}</div>
                <div class="muted">{{ $landlord_email }}</div>
            </div>
            <div class="col">
                <strong>Seeker</strong>
                <div>{{ $seeker_name }}</div>
                <div class="muted">{{ $seeker_email }}</div>
            </div>
        </div>
    </div>

    <div class="section">
        <h2>Property</h2>
        <p>{{ $listing_title }} — {{ $listing_address }}</p>
    </div>

    <div class="section">
        <h2>Financial Terms</h2>
        <ul>
            <li>Rent: {{ $rent_amount }} {{ $currency }}</li>
            <li>Deposit: {{ $deposit_amount }} {{ $currency }}</li>
            <li>Move-in date: {{ $start_date }}</li>
        </ul>
    </div>

    <div class="section">
        <h2>Terms</h2>
        <p>{{ $terms }}</p>
    </div>

    <div class="section">
        <h2>Signatures</h2>
        <p>By signing, each party agrees to the terms described in this agreement.</p>
    </div>
</body>
</html>
