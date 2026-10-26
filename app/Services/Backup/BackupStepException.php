<?php

namespace App\Services\Backup;

use RuntimeException;

/**
 * An error raised deliberately by the backup code, with a message that is safe
 * to show to the admin. Any other exception (PDO, filesystem, library) is
 * reduced to a generic message so SQL, hosts, or paths never reach the UI.
 */
class BackupStepException extends RuntimeException {}
