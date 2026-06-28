<?php

namespace App\Services;

use App\Models\DeviceToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmService
{
    private ?string $projectId;

    private ?string $credentialsPath;

    private ?string $accessToken = null;

    public function __construct()
    {
        $this->projectId = config('services.fcm.project_id') ?: null;
        $this->credentialsPath = config('services.fcm.credentials_path') ?: null;
    }

    public function send(string $token, string $title, string $body, ?array $data = null): bool
    {
        if (! $this->projectId) {
            Log::info('FCM: skipped (no project ID configured)', ['title' => $title]);

            return false;
        }

        $accessToken = $this->getAccessToken();

        if (! $accessToken) {
            Log::warning('FCM: could not obtain access token');

            return false;
        }

        $response = Http::withToken($accessToken)
            ->post("https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send", [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => $data ? array_map(fn ($v) => (string) $v, $data) : null,
                ],
            ]);

        if ($response->failed()) {
            Log::warning('FCM: push failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if ($response->status() === 404 && $this->isUnregisteredError($response->body())) {
                DeviceToken::where('token', $token)->delete();
            }

            return false;
        }

        return true;
    }

    private function getAccessToken(): ?string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        if (! $this->credentialsPath || ! file_exists($this->credentialsPath)) {
            return null;
        }

        $credentials = json_decode(file_get_contents($this->credentialsPath), true);

        if (! $credentials) {
            return null;
        }

        $jwt = $this->createJwt($credentials);

        if (! $jwt) {
            return null;
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if ($response->failed()) {
            Log::warning('FCM: OAuth token request failed', ['status' => $response->status()]);

            return null;
        }

        $this->accessToken = $response->json('access_token');

        return $this->accessToken;
    }

    private function createJwt(array $credentials): ?string
    {
        if (! isset($credentials['private_key']) || ! isset($credentials['client_email'])) {
            Log::warning('FCM: credentials missing private_key or client_email');

            return null;
        }

        $now = time();
        $header = $this->base64urlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = $this->base64urlEncode(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $toSign = "{$header}.{$payload}";
        $signature = '';
        $signResult = openssl_sign($toSign, $signature, $credentials['private_key'], 'sha256WithRSAEncryption');

        if (! $signResult) {
            Log::warning('FCM: JWT signing failed');

            return null;
        }

        return "{$toSign}.{$this->base64urlEncode($signature)}";
    }

    private function base64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function isUnregisteredError(string $responseBody): bool
    {
        $body = json_decode($responseBody, true);

        if (! $body) {
            return false;
        }

        return ($body['error']['details'][0]['errorCode'] ?? null) === 'UNREGISTERED';
    }
}
