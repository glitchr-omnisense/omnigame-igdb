<?php

namespace Omnigame\Igdb\Tests;

use Omnigame\Igdb\IgdbProvider;
use Omnigame\Testing\BareTestCase;

/** omnigame/igdb's classes load with omnisense/omnigame and Symfony's HTTP client contracts alone. */
final class BareTest extends BareTestCase
{
    protected static function entryClass(): string
    {
        return IgdbProvider::class;
    }

    protected static function requiredPackages(): array
    {
        return ['symfony/http-client-contracts'];
    }
}
