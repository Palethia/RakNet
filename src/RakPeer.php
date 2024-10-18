<?php

declare(strict_types=1);

namespace Laith98Dev\palethia\network\raknet;

use Exception;
use Laith98Dev\palethia\console\Logger;
use Laith98Dev\palethia\network\raknet\connection\ConnectionManager;
use Laith98Dev\palethia\network\raknet\connection\ConnectionState;
use Laith98Dev\palethia\network\raknet\handler\RakNetHandler;
use Laith98Dev\palethia\network\raknet\protocol\Downstream;
use Laith98Dev\palethia\network\raknet\protocol\Upstream;

abstract class RakPeer
{
    public readonly int $guid;
    public readonly int $start_time;

    public readonly UdpSocket $socket;

    public ConnectionManager $connection_manager;

    public Upstream $upstream;
    public Downstream $downstream;

    /** @var InternetAddress[] */
    public array $machine_addresses = [];

    public array $ghosted_addresses = [];

    public bool $is_running = false;

    public bool $is_minecraft_protocol = true;

    public string $response_data = "";

    public function __construct(
        public readonly InternetAddress $address,
        public ?RakNetHandler $net_handler = null,
        bool $local_machine_is_ipv4 = true
    ) {
        $this->guid = ((time() << 32) << 2 & PHP_INT_MAX >> 4);
        $this->start_time = hrtime(true);

        $this->socket = new UdpSocket($address);

        $this->connection_manager = new ConnectionManager;
        $this->upstream = new Upstream($this);
        $this->downstream = new Downstream($this);

        $this->machine_addresses = $this->getLocalAddresses($local_machine_is_ipv4);

        $this->is_running = true;
    }

    protected function getLocalAddresses(bool $is_ipv4 = true): array
    {
        $result = [];

        $internet_protocol_version_address = $is_ipv4 ? InternetAddress::INTERNET_PROTOCOL_VERSION_4 : InternetAddress::INTERNET_PROTOCOL_VERSION_6;
        $version = $is_ipv4 ? 4 : 6;

        $create_address_func = fn(?string $ip = null) => new InternetAddress($ip ?? $internet_protocol_version_address, InternetAddress::UNASSINGED_ADDRESS_PORT, $version);

        foreach (net_get_interfaces() as $interface) {
            foreach ($interface as $interface_array) {
                if (!is_array($interface_array)) {
                    continue;
                }

                foreach ($interface_array as $value) {
                    if ($value["family"] !== ($is_ipv4 ? AF_INET : AF_INET6)) {
                        continue;
                    }

                    $result[] = $create_address_func($value["address"] ?? null);
                }
            }
        }

        $result_count = count($result);
        if ($result_count < RakInfo::MAX_NUMBER_OF_MACHINE_ADDRESSES) {
            $result = array_merge($result, array_fill($result_count, RakInfo::MAX_NUMBER_OF_MACHINE_ADDRESSES - $result_count, $create_address_func()));
        }

        return $result;
    }

    public function tick(): bool
    {
        $info = $this->socket->receiveInfo();

        if (empty($info)) {
            if ($this instanceof RakClient) {
                Logger::error("Socket/server/destination not found. shutting down...");
                $this->shutdown();
                return false;
            } else {
                return true;
            }
        }

        [$stream, $address] = $info;

        if (isset($this->ghosted_addresses[strval($address)]) && $this->ghosted_addresses[strval($address)] === true) {
            $this->upstream->unconnectedPing($address);
            return true;
        }

        if (count($this->connection_manager->list) > 0) {
            foreach ($this->connection_manager->list as $connection) {
                if ($connection->state === ConnectionState::CONNECTING || $connection->state === ConnectionState::CONNECTED) {
                    $connection->tick();
                }
            }
        }

        try {
            $this->downstream->handle($stream, $address);
        } catch (Exception $error) {
            Logger::error("An error while handling downstream:", "Error Message: " . $error->getMessage(), "BackTrace: " . $error->getTraceAsString());
            if (($prev = $error->getPrevious()) !== null) {
                Logger::error("Previous Error Message: " . $prev->getMessage(), "Previous Trace: ", $prev->getTraceAsString());
            }
            $this->ghosted_addresses[strval($address)] = true;
        }

        return true;
    }

    public function shutdown(): void
    {
        $this->is_running = false;
        $this->socket->close();
        Logger::info("Palethia shutdown (" . round($this->getTime(), 3) . ")!");
    }

    public function getTime(): int
    {
        return hrtime(true) - $this->start_time;
    }
}
