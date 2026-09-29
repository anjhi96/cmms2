<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Translates an OLD-style combined role label (e.g. "KOORDINATOR WWD",
 * "PIC BUL") into the new, separate role + Area attributes (role and area
 * are no longer combined — see App\Models\User / App\Models\Area). Several
 * Feature tests were originally written directly against the combined role
 * constants that used to exist on App\Models\User; this is the single
 * translation point that keeps them working via
 * `User::factory()->create([...roleAttributes('KOORDINATOR WWD'), ...])`
 * without rewriting every individual test.
 *
 * @return array{role: string, area_id?: int}
 */
function roleAttributes(string $combinedRole): array
{
    [$baseRole, $areaName] = match ($combinedRole) {
        'ADMIN' => [App\Models\User::ROLE_ADMIN, null],
        'GUEST' => [App\Models\User::ROLE_GUEST, null],
        'KOORDINATOR', 'KOORDINATOR WWD' => [App\Models\User::ROLE_KOORDINATOR, 'WWD'],
        'KOORDINATOR BUL' => [App\Models\User::ROLE_KOORDINATOR, 'BUL'],
        'PIC', 'PIC WWD' => [App\Models\User::ROLE_PIC, 'WWD'],
        'PIC BUL' => [App\Models\User::ROLE_PIC, 'BUL'],
        default => throw new InvalidArgumentException("roleAttributes(): unknown combined role [{$combinedRole}]"),
    };

    $attributes = ['role' => $baseRole];

    if ($areaName !== null) {
        $attributes['area_id'] = App\Models\Area::firstOrCreate(
            ['name' => $areaName],
            ['slug' => strtolower($areaName), 'is_active' => true]
        )->id;
    }

    return $attributes;
}
