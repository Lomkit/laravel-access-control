<?php

namespace Lomkit\Access\Tests\Fixtures\Testing;

use Lomkit\Access\Testing\InteractsWithAccess;
use Lomkit\Access\Tests\PackageTestCase;

abstract class AccessTestCase extends PackageTestCase
{
    use InteractsWithAccess;
}
