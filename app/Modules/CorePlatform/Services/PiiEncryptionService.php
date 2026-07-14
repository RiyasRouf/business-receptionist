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
        $client = $this->client();
        $secretId = $this->secretId($tenantId);
        $project = Config::string('gcp.project_id');

        try {
            $name = SecretManagerServiceClient::secretVersionName($project, $secretId, 'latest');
            $response = $client->accessSecretVersion((new AccessSecretVersionRequest())->setName($name));

            $version = basename($response->getName());

            return [$response->getPayload()->getData(), $version];
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

        return [$keyBytes, $version];
    }

    private function getKeyVersion(string $tenantId, string $version): string
    {
        $client = $this->client();
        $project = Config::string('gcp.project_id');
        $secretId = $this->secretId($tenantId);

        $name = SecretManagerServiceClient::secretVersionName($project, $secretId, $version);
        $response = $client->accessSecretVersion((new AccessSecretVersionRequest())->setName($name));

        return $response->getPayload()->getData();
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
