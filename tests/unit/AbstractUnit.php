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

namespace Phalcon\Incubator\Mailer\Tests\Unit;

use Phalcon\Di\Di;

class AbstractUnit extends \Codeception\Test\Unit
{
    protected Di $di;

    /**
     * Creates a DI before each test
     */
    protected function setUp(): void
    {
        $this->di = new Di();
    }

    /**
     * Reset DI after each test
     */
    protected function tearDown(): void
    {
        Di::reset();
    }
}
