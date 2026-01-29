# KYC / Verified Landlord

## Privacy model
- All KYC documents are stored on private storage (`storage/app/private/kyc/{user_id}/{submission_id}`).
- Documents are never exposed via public URLs. Access is only via authenticated API download endpoints.
- Only the submitting landlord and admins can access documents.
- Admin document access is audited in `audit_logs` with the action `kyc.document.viewed`.
- No biometric/face recognition is performed—documents are only uploaded and reviewed by admins.

## Allowed files & size limits
- Allowed types: `jpg`, `jpeg`, `png`, `webp`, `pdf`.
- Max size: 10MB per file (configurable via `KYC_MAX_FILE_SIZE_KB`).

## Submission lifecycle
- Statuses: `none`, `pending`, `approved`, `rejected`, `withdrawn`.
- Only one pending submission is allowed per landlord.
- Landlords can withdraw a pending submission.
- Admins can approve/reject with notes or redact/delete documents.

## Retention & deletion
- Landlords can withdraw a pending submission; documents are removed from storage.
- Admins can redact/delete documents; submission is marked `withdrawn` and user verification is reset.
- Audit trails are retained via submission records and audit logs.

## Audit logging
- Admin document access is logged with:
  - `actor_user_id` (admin)
  - `subject_type` = `App\\Models\\KycDocument`
  - `subject_id` = document id
  - `ip_address`, `user_agent`, timestamps
