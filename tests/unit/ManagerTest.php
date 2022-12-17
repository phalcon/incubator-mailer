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

use InvalidArgumentException;
use Phalcon\Di\Di;
use Phalcon\Di\Injectable;
use Phalcon\Events\EventsAwareInterface;
use Phalcon\Incubator\Mailer\Manager;

class ManagerTest extends AbstractUnit
{
    /**
     * @test Test class inheritance from Injectable and implementing EventsAwareInterface
     */
    public function testInheritance(): void
    {
        $class = $this->createMock(Manager::class);

        $this->assertInstanceOf(Injectable::class, $class);
        $this->assertInstanceOf(EventsAwareInterface::class, $class);
    }

    /**
     * @test Test instantiating the manager with an empty array -> exception with not a string driver
     */
    public function testConstructArrayEmpty(): void
    {
        $this->expectExceptionObject(new InvalidArgumentException('Driver must be a string value set from the config'));

        new Manager([]);
    }

    /**
     * @test Test instantiating the manager with not a string driver -> InvalidArgumentException
     */
    public function testConstructDriverNotAString(): void
    {
        foreach ([true, false, [], 3.14, fopen(__FILE__, 'r'), new \stdClass(), null] as $incorrectType) {
            try {
                new Manager(['driver' => $incorrectType]);

                $this->fail("incorrect type " . gettype($incorrectType) . ' has not triggered an exception');
            } catch (InvalidArgumentException $e) {
                $this->assertSame('Driver must be a string value set from the config', $e->getMessage());
            }
        }

        $this->assertSame(7, $this->getCount());
    }

    /**
     * @test Test instantiating the manager with a driver not available by the manager -> exception
     */
    public function testConstructDriverNotAvailable(): void
    {
        $this->expectExceptionObject(new InvalidArgumentException('Driver-mail \'not-driver\' is not supported'));

        new Manager(['driver' => 'not-driver']);
    }

    /**
     * @test Test with a driver correct but no DI was created -> exception from Phalcon\Di\Exception
     */
    public function testDriverCorrectWithNoDiCreated(): void
    {
        Di::reset();

        $this->expectException(\Phalcon\Di\Exception::class);

        new Manager(['driver' => 'smtp']);
    }

    /**
     * @test Test instantiating the manager with smtp driver -> try to access methods
     */
    public function testSmtpCorrectImplementation(): void
    {
        $manager = new Manager(['driver' => 'smtp']);

        $this->assertNull($manager->getEventsManager());
        $this->assertInstanceOf('\Swift_Mailer', $manager->getSwift());
        $this->assertInstanceOf(\Swift_SmtpTransport::class, $manager->getTransport());
    }

    /**
     * @test Test instantiating the manager with sendmail driver -> try to access methods
     */
    public function testSendmailCorrectImplementation(): void
    {
        $manager = new Manager(['driver' => 'sendmail']);

        $this->assertNull($manager->getEventsManager());
        $this->assertInstanceOf('\Swift_Mailer', $manager->getSwift());
        $this->assertInstanceOf(\Swift_SendmailTransport::class, $manager->getTransport());
    }
}
