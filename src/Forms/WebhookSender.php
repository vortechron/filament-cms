<?php

namespace Vortechron\FilamentCms\Forms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * POSTs a form's JSON to its webhook, only to the public internet:
 *
 * - `https` only, no redirects, 5 seconds at most;
 * - the host is looked up once (HostResolver) and every address must be
 *   public (PublicAddress);
 * - the request is pinned to the checked address (CURLOPT_RESOLVE), so DNS
 *   cannot change between the check and the request.
 *
 * Failures are logged with the host only, never the link (it may hold a
 * secret token), and never reach the visitor.
 */
final class WebhookSender
{
    public const TIMEOUT = 5;

    public function __construct(private readonly HostResolver $resolver) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function send(string $url, array $payload): bool
    {
        $target = $this->target($url);

        if (is_string($target)) {
            Log::warning("Form webhook refused: {$target}.", ['host' => self::host($url)]);

            return false;
        }

        try {
            $response = Http::timeout(self::TIMEOUT)
                ->connectTimeout(self::TIMEOUT)
                ->withOptions(['allow_redirects' => false, 'curl' => [CURLOPT_RESOLVE => [$target['pin']]]])
                ->acceptJson()
                ->post($url, $payload);
        } catch (Throwable $exception) {
            Log::warning('Form webhook failed: no answer.', ['host' => $target['host'], 'error' => $exception::class]);

            return false;
        }

        if (! $response->successful()) {
            Log::warning('Form webhook failed: an error answer.', ['host' => $target['host'], 'status' => $response->status()]);

            return false;
        }

        return true;
    }

    /**
     * The checked host and its pin, or why the address is refused.
     *
     * @return array{host: string, pin: string}|string
     */
    private function target(string $url): array|string
    {
        $parts = parse_url($url);

        if (! is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https') {
            return 'not https';
        }

        if (! function_exists('curl_init')) {
            return 'curl is needed to pin the address';
        }

        $host = strtolower(trim($parts['host'] ?? '', '[]'));
        $addresses = $host === '' ? [] : (filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : $this->resolver->addresses($host));

        if ($addresses === []) {
            return 'the host has no address';
        }

        foreach ($addresses as $address) {
            if (! PublicAddress::check($address)) {
                return 'the host is inside a private network';
            }
        }

        $ip = $addresses[0];
        $port = (int) ($parts['port'] ?? 443);

        return ['host' => $host, 'pin' => $host.':'.$port.':'.(str_contains($ip, ':') ? "[{$ip}]" : $ip)];
    }

    private static function host(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) ? $host : null;
    }
}
