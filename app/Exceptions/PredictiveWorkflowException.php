<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A Predictive WO state rule was violated (already claimed, not OPEN, ...).
 * The message is written for the end user and shown as-is.
 */
class PredictiveWorkflowException extends RuntimeException {}
