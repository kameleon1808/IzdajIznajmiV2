<?php

namespace Tests\Feature;

use App\Models\KycDocument;
use App\Models\KycSubmission;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class KycApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_landlord_and_seeker_can_submit_kyc(): void
    {
        Storage::fake('private');

        $landlord = User::factory()->create(['role' => 'landlord']);
        $seeker = User::factory()->create(['role' => 'seeker']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->bootstrapCsrf();

        $payload = [
            'id_front' => UploadedFile::fake()->image('id-front.jpg'),
            'selfie' => UploadedFile::fake()->image('selfie.jpg'),
            'proof_of_address' => UploadedFile::fake()->create('bill.pdf', 200, 'application/pdf'),
        ];

        $this->actingAs($seeker)
            ->postJson('/api/v1/kyc/submissions', $payload)
            ->assertStatus(201)
            ->assertJsonPath('status', 'pending');

        $this->actingAs($landlord)
            ->postJson('/api/v1/kyc/submissions', $payload)
            ->assertStatus(201)
            ->assertJsonPath('status', 'pending');

        $this->assertDatabaseHas('users', [
            'id' => $seeker->id,
            'verification_status' => 'pending',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $landlord->id,
            'verification_status' => 'pending',
        ]);

        $submission = KycSubmission::where('user_id', $landlord->id)->first();
        $this->assertNotNull($submission);
        $this->assertDatabaseHas('kyc_documents', [
            'submission_id' => $submission->id,
            'doc_type' => KycDocument::TYPE_ID_FRONT,
        ]);

        $this->assertSame(
            2,
            Notification::where('user_id', $admin->id)
                ->where('type', 'kyc.submission_received')
                ->count()
        );
    }

    public function test_cannot_submit_second_pending_submission(): void
    {
        Storage::fake('private');

        $landlord = User::factory()->create(['role' => 'landlord']);
        $this->bootstrapCsrf();
        $payload = [
            'id_front' => UploadedFile::fake()->image('id-front.jpg'),
            'selfie' => UploadedFile::fake()->image('selfie.jpg'),
            'proof_of_address' => UploadedFile::fake()->create('bill.pdf', 200, 'application/pdf'),
        ];

        $this->actingAs($landlord)
            ->postJson('/api/v1/kyc/submissions', $payload)
            ->assertStatus(201);

        $this->postJson('/api/v1/kyc/submissions', $payload)
            ->assertStatus(409);
    }

    public function test_only_admin_can_approve_or_reject(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $admin = User::factory()->create(['role' => 'admin']);

        $submission = KycSubmission::create([
            'user_id' => $landlord->id,
            'status' => KycSubmission::STATUS_PENDING,
            'submitted_at' => now(),
        ]);

        $this->bootstrapCsrf();

        $this->actingAs($landlord)
            ->patchJson("/api/v1/admin/kyc/submissions/{$submission->id}/approve")
            ->assertStatus(403);

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/kyc/submissions/{$submission->id}/approve")
            ->assertOk()
            ->assertJsonPath('status', KycSubmission::STATUS_APPROVED);

        $this->assertDatabaseHas('users', [
            'id' => $landlord->id,
            'verification_status' => 'approved',
            'address_verified' => true,
        ]);

        $this->assertNotNull($landlord->fresh()->verified_at);
    }

    public function test_document_download_forbidden_to_non_owner_or_admin(): void
    {
        Storage::fake('private');

        $landlord = User::factory()->create(['role' => 'landlord']);
        $other = User::factory()->create(['role' => 'seeker']);

        $document = $this->createSubmissionWithDocument($landlord, KycDocument::TYPE_ID_FRONT);

        $this->actingAs($other)
            ->get("/api/v1/kyc/documents/{$document->id}")
            ->assertStatus(403);
    }

    public function test_admin_document_access_is_audited(): void
    {
        Storage::fake('private');

        $landlord = User::factory()->create(['role' => 'landlord']);
        $admin = User::factory()->create(['role' => 'admin']);

        $document = $this->createSubmissionWithDocument($landlord, KycDocument::TYPE_SELFIE);

        $this->actingAs($admin)
            ->get("/api/v1/kyc/documents/{$document->id}")
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'kyc.document.viewed',
            'subject_type' => KycDocument::class,
            'subject_id' => $document->id,
        ]);
    }

    private function createSubmissionWithDocument(User $user, string $docType): KycDocument
    {
        $submission = KycSubmission::create([
            'user_id' => $user->id,
            'status' => KycSubmission::STATUS_PENDING,
            'submitted_at' => now(),
        ]);

        $path = "kyc/{$user->id}/{$submission->id}/{$docType}.jpg";
        Storage::disk('private')->put($path, 'file');

        return KycDocument::create([
            'submission_id' => $submission->id,
            'user_id' => $user->id,
            'doc_type' => $docType,
            'original_name' => 'doc.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 4,
            'disk' => 'private',
            'path' => $path,
        ]);
    }

    private function bootstrapCsrf(): void
    {
        $this->withCredentials();
        $response = $this->get('/sanctum/csrf-cookie');
        $response->assertNoContent();
        $this->stashCookiesFrom($response);
    }

    private function stashCookiesFrom(TestResponse $response): void
    {
        $cookies = collect($response->headers->getCookies())
            ->mapWithKeys(fn ($cookie) => [$cookie->getName() => $cookie->getValue()])
            ->all();

        if (empty($cookies)) {
            return;
        }

        $this->withUnencryptedCookies($cookies);

        if (isset($cookies['XSRF-TOKEN'])) {
            $this->withHeader('X-XSRF-TOKEN', $cookies['XSRF-TOKEN']);
        }
    }
}
