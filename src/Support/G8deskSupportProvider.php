<?php

declare(strict_types=1);

namespace G8scope\Core\Support;

use G8scope\Core\Contracts\SupportProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * g8desk driver for the SupportProvider seam (spec §15). Creates/links a g8desk
 * ticket from an already-scrubbed issue context and returns its reference
 * (DESK-YYYY-NNNNN). PII must be scrubbed by the caller before reaching here.
 */
class G8deskSupportProvider implements SupportProvider
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $token = null,
        private readonly ?string $organization = null,
        private readonly ?string $product = null,
    ) {}

    public static function fromConfig(array $config): self
    {
        return new self(
            (string) ($config['base_url'] ?? ''),
            $config['token'] ?? null,
            $config['organization'] ?? null,
            $config['product'] ?? null,
        );
    }

    public function attach(string $issueUuid, array $scrubbedContext): string
    {
        $response = Http::baseUrl($this->baseUrl)
            ->withToken((string) $this->token)
            ->asJson()
            ->post('/tickets', [
                'organization' => $this->organization,
                'product' => $this->product,
                'subject' => "g8scope issue {$issueUuid}",
                'context' => $scrubbedContext,
                'source' => 'g8scope',
                'external_ref' => $issueUuid,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("g8desk ticket creation failed: HTTP {$response->status()}");
        }

        $reference = $response->json('reference');
        if (! is_string($reference) || $reference === '') {
            throw new RuntimeException('g8desk response missing ticket reference.');
        }

        return $reference;
    }
}
