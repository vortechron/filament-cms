<?php

namespace Vortechron\FilamentCms\Forms;

use Closure;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\TransferStats;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * POSTs a form's JSON to its webhook, only to the public internet:
 *
 * - `https` only, no redirects, no proxy from the environment, 5 seconds
 *   at most, always through curl (Guzzle's CurlHandler);
 * - the host must be a plain ASCII name (no trailing dot, its last part a
 *   word) or an IP address written in full;
 * - the name is looked up once (HostResolver) and every address must be
 *   public (PublicAddress);
 * - curl connects to the checked address whatever host it reads from the
 *   link (CURLOPT_CONNECT_TO with any host and port), so DNS cannot change
 *   between the check and the request;
 * - every address curl reports it connected to (CURLINFO_PRIMARY_IP, read
 *   in `on_stats`) must be the checked one, and there must be one, or the
 *   send counts as failed.
 *
 * Failures are logged with the host only, never the link (it may hold a
 * secret token), and never reach the visitor.
 */
final class WebhookSender
{
    public const TIMEOUT = 5;

    /**
     * A host name: ASCII labels, the last one starting with a letter, so a
     * number such as 2130706433 or 127.1 is never read as an address.
     */
    private const NAME = '/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z](?:[a-z0-9-]{0,61}[a-z0-9])?$/';

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

        $connected = [];

        try {
            $response = $this->post($url, $payload, $target, function (TransferStats $stats) use (&$connected): void {
                $ip = $stats->getHandlerStats()['primary_ip'] ?? null;

                if (is_string($ip) && $ip !== '') {
                    $connected[] = $ip;
                }
            });
        } catch (Throwable $exception) {
            Log::warning('Form webhook failed: no answer.', ['host' => $target['host'], 'error' => $exception::class]);

            return false;
        }

        if (! self::onlyAt($connected, $target['ip'])) {
            Log::warning('Form webhook failed: connected to another address.', ['host' => $target['host']]);

            return false;
        }

        if (! $response->successful()) {
            Log::warning('Form webhook failed: an error answer.', ['host' => $target['host'], 'status' => $response->status()]);

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{host: string, ip: string, port: int}  $target
     * @param  Closure(TransferStats): void  $onStats
     */
    private function post(string $url, array $payload, array $target, Closure $onStats): Response
    {
        $address = str_contains($target['ip'], ':') ? "[{$target['ip']}]" : $target['ip'];

        return Http::setHandler(new CurlHandler)
            ->timeout(self::TIMEOUT)
            ->connectTimeout(self::TIMEOUT)
            ->withOptions([
                'allow_redirects' => false,
                'proxy' => '',
                'curl' => [CURLOPT_CONNECT_TO => ["::{$address}:{$target['port']}"]],
                'on_stats' => $onStats,
            ])
            ->acceptJson()
            ->post($url, $payload);
    }

    /**
     * The checked host, address and port, or why the link is refused.
     *
     * @return array{host: string, ip: string, port: int}|string
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

        $raw = strtolower($parts['host'] ?? '');
        $literal = str_starts_with($raw, '[') && str_ends_with($raw, ']') ? substr($raw, 1, -1) : $raw;
        $isAddress = filter_var($literal, FILTER_VALIDATE_IP) !== false && ($literal === $raw) === ! str_contains($literal, ':');

        if (! $isAddress && preg_match(self::NAME, $raw) !== 1) {
            return 'the host is not a plain name';
        }

        $addresses = $isAddress ? [$literal] : $this->resolver->addresses($raw);

        if ($addresses === []) {
            return 'the host has no address';
        }

        foreach ($addresses as $address) {
            if (! PublicAddress::check($address)) {
                return 'the host is inside a private network';
            }
        }

        return ['host' => $raw, 'ip' => $addresses[0], 'port' => (int) ($parts['port'] ?? 443)];
    }

    /**
     * @param  list<string>  $connected
     */
    private static function onlyAt(array $connected, string $checked): bool
    {
        $checked = @inet_pton($checked);

        foreach ($connected as $ip) {
            if (@inet_pton($ip) !== $checked) {
                return false;
            }
        }

        return $connected !== [] && $checked !== false;
    }

    private static function host(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) ? $host : null;
    }
}
