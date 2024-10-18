<?php

declare(strict_types=1);

namespace Laith98Dev\palethia\network\raknet;

use RuntimeException;

final class InternetAddress
{
    public const INTERNET_PROTOCOL_VERSION_4 = "0.0.0.0";
    public const INTERNET_PROTOCOL_VERSION_6 = "::";

    public const UNASSINGED_ADDRESS_PORT = 1024;

    public readonly string $ip;

    public static function random(int $version): self
    {
        return new self($version === 4 ? self::INTERNET_PROTOCOL_VERSION_4 : self::INTERNET_PROTOCOL_VERSION_4, mt_rand(self::UNASSINGED_ADDRESS_PORT + 1, 65535), 4);
    }

    public function __construct(
        string $ip,
        public readonly int $port,
        public readonly int $version,
    ) {
        $ip_filter_func = function (mixed $value, int $filter = FILTER_VALIDATE_IP) use ($version): mixed {
            if ($filter === FILTER_VALIDATE_IP) {
                return filter_var($value, $filter, $version === 4 ? FILTER_FLAG_IPV4 : FILTER_FLAG_IPV6);
            } else if ($filter === FILTER_VALIDATE_DOMAIN) {
                return filter_var($value, $filter, FILTER_FLAG_HOSTNAME);
            }

            return false;
        };
        $filtered_ip = $ip_filter_func($ip, FILTER_VALIDATE_IP);
        $success = false;

        if ($filtered_ip !== false) {
            $success = true;
        } else if ($version === 4 && $filtered_ip === false) {
            $filtered_ip = $ip_filter_func($ip, FILTER_VALIDATE_DOMAIN);
            if ($filtered_ip !== false) {
                $success = true;

                $hostnames = gethostbynamel($filtered_ip);

                if (empty($hostnames) || $hostnames === false) {
                    throw new RuntimeException("Invalid ip address $filtered_ip.");
                }

                $filtered_ip = $hostnames[0];
            }
        }/* else if ($version === 0 && $filtered_ip === false && preg_match('/^[0-9.]+$/', $ip)) { // caused from socket
            $success = true;
            $filtered_ip = $ip;
        }*/
        // TODO: CHECK IF ^^^^^^^^^^^^^^^^^ IS STILL REQUIRED (it is not but it's better to be safe after some tests)

        if (!$success && $ip !== "0.0.0.0") {
            throw new RuntimeException("Invalid ip $ip");
        }

        $this->ip = $filtered_ip;
    }

    public function equals(InternetAddress $value): bool
    {
        return $value->ip === $this->ip && $value->port === $this->port;
    }

    public function __toString(): string
    {
        return $this->ip . ":" . $this->port;
    }
}
