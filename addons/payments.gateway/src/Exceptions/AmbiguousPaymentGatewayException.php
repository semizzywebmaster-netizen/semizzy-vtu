<?php

namespace Semizzy\Addons\Payments\Exceptions;

use RuntimeException;

/** Signals that a provider may have accepted a financial mutation. */
final class AmbiguousPaymentGatewayException extends RuntimeException
{
}
