<?php

namespace Vortechron\FilamentCms\Forms;

/**
 * Looks up the IP addresses of a host name, for the webhook's check
 * (WebhookSender). Tests bind a fake so they need no network.
 */
interface HostResolver
{
    /**
     * Every IPv4 and IPv6 address the name has, or [] when it has none.
     *
     * @return list<string>
     */
    public function addresses(string $host): array;
}
