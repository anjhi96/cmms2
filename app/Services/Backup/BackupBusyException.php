<?php

namespace App\Services\Backup;

use RuntimeException;

/** Thrown when a backup of the same type is already running (lock is held). */
class BackupBusyException extends RuntimeException {}
