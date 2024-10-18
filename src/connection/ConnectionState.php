<?php

declare(strict_types=1);

namespace Laith98Dev\palethia\network\raknet\connection;

enum ConnectionState
{
	case NOT_CONNECTED;

	case CONNECTING;

	case ACCEPTED_CONNECTION_INVALID_PORT;

	case INCOMING_CONNECTION_INVALID_PORT;

	case DISCONNECTED;

	case CONNECTED;
}
