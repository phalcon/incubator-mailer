<?php

/**
 * This file is part of the Phalcon Framework.
 *
 * (c) Phalcon Team <team@phalcon.io>
 *
 * For the full copyright and license information, please view the LICENSE.txt
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Phalcon\Incubator\Mailer\Tests\Unit\Manager;

use Codeception\Test\Unit;
use Phalcon\Di\Injectable;
use Phalcon\Incubator\Mailer\Manager;

final class ManagerTest extends Unit
{
    public function testImplementation(): void
    {
        $class = $this->createMock(Manager::class);
        $this->assertInstanceOf(Injectable::class, $class);
    }
}
