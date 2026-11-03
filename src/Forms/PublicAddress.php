<?php

namespace Vortechron\FilamentCms\Forms;

/**
 * Whether an IP address is on the public internet: not private, loopback,
 * link-local, carrier-grade NAT, reserved, documentation or multicast, and
 * not an IPv6 form of such an IPv4 address (mapped, NAT64, 6to4).
 */
final class PublicAddress
{
    public static function check(string $ip): bool
    {
        $packed = @inet_pton($ip);

        if ($packed === false || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        $inner = self::embeddedIpv4($packed);

        if ($inner !== null && ! self::check($inner)) {
            return false;
        }

        if (strlen($packed) === 4 ? (ord($packed[0]) & 0xF0) === 0xE0 : ord($packed[0]) === 0xFF) {
            return false;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_GLOBAL_RANGE) !== false;
    }

    /**
     * The IPv4 address an IPv6 address carries, if any.
     */
    private static function embeddedIpv4(string $packed): ?string
    {
        if (strlen($packed) !== 16) {
            return null;
        }

        $inner = match (true) {
            str_starts_with($packed, str_repeat("\0", 10)."\xFF\xFF") => substr($packed, 12, 4),
            str_starts_with($packed, "\x00\x64\xFF\x9B".str_repeat("\0", 8)) => substr($packed, 12, 4),
            str_starts_with($packed, "\x20\x02") => substr($packed, 2, 4),
            default => null,
        };

        return $inner === null ? null : (string) inet_ntop($inner);
    }
}
