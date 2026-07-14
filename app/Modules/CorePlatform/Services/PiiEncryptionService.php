<?php

namespace App\Modules\CorePlatform\Services;

use App\Modules\CorePlatform\Contracts\PiiEncryptionServiceInterface;
use Google\Cloud\SecretManager\V1\AccessSecretVersionRequest;
use Google\Cloud\SecretManager\V1\AddSecretVersionRequest;
use Google\Cloud\SecretManager\V1\Client\SecretManagerServiceClient;
use Google\Cloud\SecretManager\V1\CreateSecretRequest;
use Google\Cloud\SecretManager\V1\Replication;
use Google\Cloud\SecretManager\V1\Replication\Automatic;
use Google\Cloud\SecretManager\V1\Secret;
use Google\Cloud\SecretManager\V1\SecretPayload;
use Illuminate\Support\Facades\Config;
use RuntimeException;

/**
 * AES-256-GCM envelope encryption for PII fields (ADR-035, ADR-065).
 * Per-tenant key stored in GCP Secret Manager. Rotation is handled via
 * Secret Manager's native versioning: encrypt() always uses the latest
 * version; decrypt() reads whichever version the ciphertext was written
 * with, so rotating the key does not require re-encrypting old data.
 */
class PiiEncryptionService implements PiiEncryptionServiceInterface
{
    private const CIPHER = 'aes-256-gcm';

    private const KEY_BYTES = 32;

    private const NONCE_BYTES = 12;

    private const TAG_BYTES = 16;

    private ?SecretManagerServiceClient $client = null;

    /**
     * Per-request key cache. Bound as a singleton, so this lives for one
     * HTTP request/CLI invocation — not persisted across requests. Without
     * this, every encrypt()/decrypt() call (e.g. reading a Lead's
     * fields_json, which decrypts N fields individually) does N live
     * Secret Manager round-trips even when they're all the same tenant's
     * current key. Found via a real timeout: 21 conversation turns each
     * re-decrypting an already-complete lead's 6 fields blew well past
     * the 800ms/turn target (ADR-047) from GCP latency alone.
     *
     * @var array<string, string>
     */
    private array $keyCache = [];

    public function encrypt(string $tenantId, string $plaintext): string
    {
        [$key, $version] = $this->getOrCreateLatestKey($tenantId);

        $nonce = random_bytes(self::NONCE_BYTES);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            self::TAG_BYTES
        );

        if ($ciphertext === false) {
            throw new RuntimeException('PII encryption failed.');
        }

        return $version.':'.base64_encode($nonce.$tag.$ciphertext);
    }

    public function decrypt(string $tenantId, string $payload): string
    {
        [$version, $encoded] = explode(':', $payload, 2);

        $key = $this->getKeyVersion($tenantId, $version);
        $raw = base64_decode($encoded, true);

        if ($raw === false || strlen($raw) < self::NONCE_BYTES + self::TAG_BYTES) {
            throw new RuntimeException('Malformed PII ciphertext.');
        }

        $nonce = substr($raw, 0, self::NONCE_BYTES);
        $tag = substr($raw, self::NONCE_BYTES, self::TAG_BYTES);
        $ciphertext = substr($raw, self::NONCE_BYTES + self::TAG_BYTES);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        if ($plaintext === false) {
            throw new RuntimeException('PII decryption failed — key rotated or ciphertext corrupted.');
        }

        return $plaintext;
    }

    /**
     * @return array{0: string, 1: string} [rawKeyBytes, versionNumber]
     */
    private function getOrCreateLatestKey(string $tenantId): array
    {
        $cacheKey = "latest:{$tenantId}";

        if (isset($this->keyCache[$cacheKey])) {
            return [$this->keyCache[$cacheKey], $this->keyCache["{$cacheKey}:version"]];
        }

        $client = $this->client();
        $secretId = $this->secretId($tenantId);
        $project = Config::string('gcp.project_id');

        try {
            $name = SecretManagerServiceClient::secretVersionName($project, $secretId, 'latest');
            $response = $client->accessSecretVersion((new AccessSecretVersionRequest())->setName($name));

            $version = basename($response->getName());
            $key = $response->getPayload()->getData();

            $this->keyCache[$cacheKey] = $key;
            $this->keyCache["{$cacheKey}:version"] = $version;
            $this->keyCache["version:{$tenantId}:{$version}"] = $key;

            return [$key, $version];
        } catch (\Throwable) {
            return $this->createTenantKey($tenantId);
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function createTenantKey(string $tenantId): array
    {
        $client = $this->client();
        $secretId = $this->secretId($tenantId);
        $project = Config::string('gcp.project_id');
        $parent = SecretManagerServiceClient::projectName($project);

        try {
            $secret = (new Secret())->setReplication(
                (new Replication())->setAutomatic(new Automatic())
            );

            $client->createSecret(
                (new CreateSecretRequest())->setParent($parent)->setSecretId($secretId)->setSecret($secret)
            );
        } catch (\Throwable) {
            // Secret container already exists — fall through to add a version.
        }

        $keyBytes = random_bytes(self::KEY_BYTES);
        $secretName = SecretManagerServiceClient::secretName($project, $secretId);

        $response = $client->addSecretVersion(
            (new AddSecretVersionRequest())
                ->setParent($secretName)
                ->setPayload((new SecretPayload())->setData($keyBytes))
        );

        $version = basename($response->getName());

        $this->keyCache["latest:{$tenantId}"] = $keyBytes;
        $this->keyCache["latest:{$tenantId}:version"] = $version;
        $this->keyCache["version:{$tenantId}:{$version}"] = $keyBytes;

        return [$keyBytes, $version];
    }

    private function getKeyVersion(string $tenantId, string $version): string
    {
        $cacheKey = "version:{$tenantId}:{$version}";

        if (isset($this->keyCache[$cacheKey])) {
            return $this->keyCache[$cacheKey];
        }

        $client = $this->client();
        $project = Config::string('gcp.project_id');
        $secretId = $this->secretId($tenantId);

        $name = SecretManagerServiceClient::secretVersionName($project, $secretId, $version);
        $response = $client->accessSecretVersion((new AccessSecretVersionRequest())->setName($name));

        $key = $response->getPayload()->getData();
        $this->keyCache[$cacheKey] = $key;

        return $key;
    }

    private function secretId(string $tenantId): string
    {
        return 'pii-tenant-'.str_replace('-', '', $tenantId);
    }

    private function client(): SecretManagerServiceClient
    {
        if ($this->client === null) {
            $this->client = new SecretManagerServiceClient([
                'credentials' => Config::string('gcp.credentials_path'),
            ]);
        }

        return $this->client;
    }
}
