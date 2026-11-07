<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A Small PM schedule rule was violated (not this week, already started, ...).
 * The message is written for the end user and shown as-is.
 */
class SmallPmWorkflowException extends RuntimeException {}
