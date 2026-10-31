<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;

/**
 * Raw sheet reader for the warehouse issue file. Header validation and row
 * parsing live in SparepartInjectionService so they are shared and testable.
 */
class SparepartInjectionSheet implements ToArray
{
    public function array(array $array): void {}
}
