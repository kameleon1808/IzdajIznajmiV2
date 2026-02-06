<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\LandlordListingController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\RatingReportController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\GeocodeSuggestController;
use App\Http\Controllers\Admin\RatingAdminController;
use App\Http\Controllers\MessageReportController;
use App\Http\Controllers\ListingReportController;
use App\Http\Controllers\Admin\ModerationController;
use App\Http\Controllers\Admin\KpiController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\GeocodingController;
use App\Http\Controllers\ChatAttachmentController;
use App\Http\Controllers\ChatSignalController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationPreferenceController;
use App\Http\Controllers\SavedSearchController;
use App\Http\Controllers\ListingLocationController;
use App\Http\Controllers\ViewingRequestController;
use App\Http\Controllers\ViewingSlotController;
use App\Http\Controllers\RentalTransactionController;
use App\Http\Controllers\TransactionContractController;
use App\Http\Controllers\ContractSignatureController;
use App\Http\Controllers\ContractPdfController;
use App\Http\Controllers\TransactionPaymentController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\KycSubmissionController;
use App\Http\Controllers\KycDocumentController;
use App\Http\Controllers\Admin\KycSubmissionAdminController;
use App\Http\Controllers\Admin\TransactionAdminController;
use Illuminate\Support\Facades\Route;

$authRoutes = function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
};

$apiRoutes = function () use ($authRoutes) {
    Route::prefix('auth')->group($authRoutes);

    Route::get('/health', [HealthController::class, 'liveness']);
    Route::get('/health/ready', [HealthController::class, 'readiness']);
    Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle']);

    Route::get('/listings', [ListingController::class, 'index'])->middleware('throttle:listings_search');
    Route::get('/listings/{listing}', [ListingController::class, 'show']);
    Route::prefix('search')->group(function () {
        Route::get('/listings', [SearchController::class, 'listings'])->middleware('throttle:listings_search');
        Route::get('/suggest', [SearchController::class, 'suggest'])->middleware('throttle:listings_search');
    });
    Route::get('/users/{user}', [UserProfileController::class, 'show']);
    Route::get('/users/{user}/ratings', [RatingController::class, 'userRatings']);
    Route::get('/geocode', [GeocodingController::class, 'lookup'])->middleware('throttle:listings_search');
    Route::get('/geocode/suggest', [GeocodeSuggestController::class, 'suggest'])->middleware('throttle:geocode_suggest');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/landlord/listings', [LandlordListingController::class, 'index']);
        Route::post('/landlord/listings', [LandlordListingController::class, 'store'])->middleware('throttle:landlord_write');
        Route::put('/landlord/listings/{listing}', [LandlordListingController::class, 'update'])->middleware('throttle:landlord_write');
        Route::patch('/landlord/listings/{listing}/publish', [LandlordListingController::class, 'publish'])->middleware('throttle:landlord_write');
        Route::patch('/landlord/listings/{listing}/unpublish', [LandlordListingController::class, 'unpublish'])->middleware('throttle:landlord_write');
        Route::patch('/landlord/listings/{listing}/archive', [LandlordListingController::class, 'archive'])->middleware('throttle:landlord_write');
        Route::patch('/landlord/listings/{listing}/restore', [LandlordListingController::class, 'restore'])->middleware('throttle:landlord_write');
        Route::patch('/landlord/listings/{listing}/mark-rented', [LandlordListingController::class, 'markRented'])->middleware('throttle:landlord_write');
        Route::patch('/landlord/listings/{listing}/mark-available', [LandlordListingController::class, 'markAvailable'])->middleware('throttle:landlord_write');

        Route::post('/listings/{listing}/apply', [ApplicationController::class, 'apply'])->middleware('throttle:applications');
        Route::get('/seeker/applications', [ApplicationController::class, 'seekerIndex']);
        Route::get('/landlord/applications', [ApplicationController::class, 'landlordIndex']);
        Route::patch('/applications/{application}', [ApplicationController::class, 'update']);

        Route::get('/conversations', [ConversationController::class, 'index']);
        Route::get('/listings/{listing}/conversation', [ConversationController::class, 'conversationForListing']);
        Route::post('/listings/{listing}/conversation', [ConversationController::class, 'conversationForListing']);
        Route::get('/listings/{listing}/messages', [ConversationController::class, 'messagesForListing']);
        Route::post('/listings/{listing}/messages', [ConversationController::class, 'sendMessageForListing'])
            ->middleware(['throttle:chat_messages', 'chat_attachments']);
        Route::post('/applications/{application}/conversation', [ConversationController::class, 'conversationForApplication']);
        Route::get('/conversations/{conversation}/messages', [ConversationController::class, 'messages']);
        Route::get('/conversations/{conversation}', [ConversationController::class, 'show']);
        Route::post('/conversations/{conversation}/messages', [ConversationController::class, 'send'])
            ->middleware(['throttle:chat_messages', 'chat_attachments']);
        Route::post('/conversations/{conversation}/read', [ConversationController::class, 'markRead']);
        Route::post('/conversations/{conversation}/typing', [ChatSignalController::class, 'typing']);
        Route::get('/conversations/{conversation}/typing', [ChatSignalController::class, 'typingStatus']);
        Route::get('/chat/attachments/{attachment}', [ChatAttachmentController::class, 'show'])->name('chat.attachments.show');
        Route::get('/chat/attachments/{attachment}/thumb', [ChatAttachmentController::class, 'thumb'])->name('chat.attachments.thumb');

        Route::post('/presence/ping', [ChatSignalController::class, 'presencePing']);
        Route::get('/users/{user}/presence', [ChatSignalController::class, 'presenceStatus']);

        Route::post('/listings/{listing}/ratings', [RatingController::class, 'store']);
        Route::get('/me/ratings', [RatingController::class, 'myRatings']);
        Route::post('/ratings/{rating}/report', [RatingReportController::class, 'store']);
        Route::post('/messages/{message}/report', [MessageReportController::class, 'store']);
        Route::post('/listings/{listing}/report', [ListingReportController::class, 'store']);
        Route::patch('/listings/{listing}/location', [ListingLocationController::class, 'update'])->middleware('throttle:landlord_write');
        Route::post('/listings/{listing}/location/reset', [ListingLocationController::class, 'reset'])->middleware('throttle:landlord_write');

        Route::get('/listings/{listing}/viewing-slots', [ViewingSlotController::class, 'index']);
        Route::post('/listings/{listing}/viewing-slots', [ViewingSlotController::class, 'store'])->middleware('throttle:landlord_write');
        Route::patch('/viewing-slots/{viewingSlot}', [ViewingSlotController::class, 'update'])->middleware('throttle:landlord_write');
        Route::delete('/viewing-slots/{viewingSlot}', [ViewingSlotController::class, 'destroy'])->middleware('throttle:landlord_write');

        Route::post('/viewing-slots/{viewingSlot}/request', [ViewingRequestController::class, 'store'])->middleware('throttle:viewing_requests');
        Route::get('/seeker/viewing-requests', [ViewingRequestController::class, 'seekerIndex']);
        Route::get('/landlord/viewing-requests', [ViewingRequestController::class, 'landlordIndex']);
        Route::patch('/viewing-requests/{viewingRequest}/confirm', [ViewingRequestController::class, 'confirm']);
        Route::patch('/viewing-requests/{viewingRequest}/reject', [ViewingRequestController::class, 'reject']);
        Route::patch('/viewing-requests/{viewingRequest}/cancel', [ViewingRequestController::class, 'cancel']);
        Route::get('/viewing-requests/{viewingRequest}/ics', [ViewingRequestController::class, 'ics']);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
        Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);

        Route::get('/notification-preferences', [NotificationPreferenceController::class, 'show']);
        Route::put('/notification-preferences', [NotificationPreferenceController::class, 'update']);

        Route::get('/saved-searches', [SavedSearchController::class, 'index']);
        Route::post('/saved-searches', [SavedSearchController::class, 'store']);
        Route::put('/saved-searches/{savedSearch}', [SavedSearchController::class, 'update']);
        Route::delete('/saved-searches/{savedSearch}', [SavedSearchController::class, 'destroy']);

        Route::post('/transactions', [RentalTransactionController::class, 'store']);
        Route::get('/transactions/{transaction}', [RentalTransactionController::class, 'show']);
        Route::post('/transactions/{transaction}/contracts', [TransactionContractController::class, 'store']);
        Route::get('/transactions/{transaction}/contracts/latest', [TransactionContractController::class, 'latest']);
        Route::post('/contracts/{contract}/sign', [ContractSignatureController::class, 'sign']);
        Route::get('/contracts/{contract}/pdf', [ContractPdfController::class, 'show'])->name('contracts.pdf');
        Route::post('/transactions/{transaction}/payments/deposit/session', [TransactionPaymentController::class, 'createDepositSession']);
        Route::post('/transactions/{transaction}/move-in/confirm', [TransactionPaymentController::class, 'confirmMoveIn']);

        Route::post('/kyc/submissions', [KycSubmissionController::class, 'store']);
        Route::get('/kyc/submissions/me', [KycSubmissionController::class, 'me']);
        Route::post('/kyc/submissions/{submission}/withdraw', [KycSubmissionController::class, 'withdraw']);
        Route::get('/kyc/documents/{document}', [KycDocumentController::class, 'show'])->name('kyc.documents.show');

        Route::prefix('admin')->group(function () {
            Route::post('/impersonate/stop', [ImpersonationController::class, 'stop']);
            Route::middleware('role:admin')->group(function () {
                Route::get('/kyc/submissions', [KycSubmissionAdminController::class, 'index']);
                Route::get('/kyc/submissions/{submission}', [KycSubmissionAdminController::class, 'show']);
                Route::patch('/kyc/submissions/{submission}/approve', [KycSubmissionAdminController::class, 'approve']);
                Route::patch('/kyc/submissions/{submission}/reject', [KycSubmissionAdminController::class, 'reject']);
                Route::delete('/kyc/submissions/{submission}/redact', [KycSubmissionAdminController::class, 'redact']);
                Route::get('/ratings', [RatingAdminController::class, 'index']);
                Route::get('/ratings/{rating}', [RatingAdminController::class, 'show']);
                Route::delete('/ratings/{rating}', [RatingAdminController::class, 'destroy']);
                Route::patch('/users/{user}/flag-suspicious', [RatingAdminController::class, 'flagUser']);
                Route::get('/moderation/queue', [ModerationController::class, 'queue']);
                Route::get('/moderation/reports/{report}', [ModerationController::class, 'show']);
                Route::patch('/moderation/reports/{report}', [ModerationController::class, 'update']);
                Route::get('/kpi/summary', [KpiController::class, 'summary']);
                Route::get('/kpi/conversion', [KpiController::class, 'conversion']);
                Route::get('/kpi/trends', [KpiController::class, 'trends']);
                Route::post('/impersonate/{user}', [ImpersonationController::class, 'start'])->whereNumber('user');
                Route::get('/transactions', [TransactionAdminController::class, 'index']);
                Route::get('/transactions/{transaction}', [TransactionAdminController::class, 'show']);
                Route::patch('/transactions/{transaction}/mark-disputed', [TransactionAdminController::class, 'markDisputed']);
                Route::patch('/transactions/{transaction}/cancel', [TransactionAdminController::class, 'cancel']);
                Route::post('/transactions/{transaction}/payout', [TransactionAdminController::class, 'payout']);
            });
        });
    });
};

Route::prefix('v1')->group($apiRoutes);
$apiRoutes();
