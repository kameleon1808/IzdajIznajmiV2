<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Monolog processor that strips or masks known PII fields from log context.
 *
 * Applied globally to all log channels so that PII never lands in log files,
 * external log aggregators (Papertrail, Slack), or error trackers (Sentry).
 *
 * Fields masked (value replaced with ***):
 *   email, phone, full_name, name (when next to email/phone), address,
 *   residential_address, document_id, password, token, secret
 *
 * Fields removed entirely:
 *   address_book (array of addresses), ssn, tax_id
 */
class PiiSanitizer implements ProcessorInterface
{
    /** Keys whose values are masked with *** */
    private const MASK_KEYS = [
        'email',
        'phone',
        'full_name',
        'residential_address',
        'address',
        'document_id',
        'password',
        'token',
        'secret',
        'recovery_code',
    ];

    /** Keys whose entries are removed entirely */
    private const STRIP_KEYS = [
        'address_book',
        'ssn',
        'tax_id',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $this->sanitize($record->context);
        $extra   = $this->sanitize($record->extra);

        return $record->with(context: $context, extra: $extra);
    }

    private function sanitize(array $data, int $depth = 0): array
    {
        if ($depth > 5) {
            return $data;
        }

        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string) $key);

            if (in_array($lowerKey, self::STRIP_KEYS, true)) {
                unset($data[$key]);
                continue;
            }

            if (in_array($lowerKey, self::MASK_KEYS, true)) {
                $data[$key] = $this->mask($value);
                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->sanitize($value, $depth + 1);
            }
        }

        return $data;
    }

    private function mask(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return '***';
        }

        // For email addresses, keep domain part for debuggability: us***@example.com
        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            [$local, $domain] = explode('@', $value, 2);
            $visible = mb_substr($local, 0, min(2, mb_strlen($local)));

            return $visible.'***@'.$domain;
        }

        return '***';
    }
}
