<?php
namespace Leadgen\ExactTarget;

use Leadgen\Customer\Customer;
use LeroyMerlin\ExactTarget\Client;
use LeroyMerlin\ExactTarget\Exception\ExactTargetClientException;
use Mockery as m;
use Mongolid\Cursor\Cursor;
use PHPUnit_Framework_TestCase;
use Psr\Log\LoggerInterface;

class CustomerUpdaterTest extends PHPUnit_Framework_TestCase
{
    public function sendMethodDataProvider()
    {
        return [
            // ------------------------------
            'empty customers' => [
                '$customers' => [],
                '$dataExtension' => 'fooBar',
                '$expectations' => [
                    'key' => 'fooBar',
                    'data' => []
                ]
            ],

            // ------------------------------
            'some customers' => [
                '$customers' => [
                    [
                        'email' => 'johndoe@gmail.com',
                        'docNumber' => '1234567'
                    ],
                    [
                        'email' => 'example@example.com',
                        'docNumber' => '456789'
                    ],
                    [
                        'email' => 'random@customer.com'
                    ]
                ],
                '$dataExtension' => 'some-thing',
                '$expectations' => [
                    'key' => 'some-thing',
                    'data' => [
                        [
                            'keys' => ['Email' => 'johndoe@gmail.com'],
                            'values' => ['Email' => 'johndoe@gmail.com']
                        ],
                        [
                            'keys' => ['Email' => 'example@example.com'],
                            'values' => ['Email' => 'example@example.com']
                        ],
                        [
                            'keys' => ['Email' => 'random@customer.com'],
                            'values' => ['Email' => 'random@customer.com']
                        ],
                    ]
                ]
            ],

            // ------------------------------
            'critical error' => [
                '$customers' => [
                    ['email' => 'johndoe@gmail.com'],
                    ['email' => 'random@customer.com']
                ],
                '$dataExtension' => 'some-thing',
                '$expectations' => [
                    'key' => 'some-thing',
                    'data' => [
                        [
                            'keys' => ['Email' => 'johndoe@gmail.com'],
                            'values' => ['Email' => 'johndoe@gmail.com']
                        ],
                        [
                            'keys' => ['Email' => 'random@customer.com'],
                            'values' => ['Email' => 'random@customer.com']
                        ],
                    ]
                ],
                '$expectedResult' => false,
                '$error' => new ExactTargetClientException('Critical error!')
            ],

            // ------------------------------
            'non critical error' => [
                '$customers' => [
                    ['email' => 'johndoe@gmail.com'],
                    ['email' => 'random@customer.com']
                ],
                '$dataExtension' => 'some-thing-else',
                '$expectations' => [
                    'key' => 'some-thing-else',
                    'data' => [
                        [
                            'keys' => ['Email' => 'johndoe@gmail.com'],
                            'values' => ['Email' => 'johndoe@gmail.com']
                        ],
                        [
                            'keys' => ['Email' => 'random@customer.com'],
                            'values' => ['Email' => 'random@customer.com']
                        ],
                    ]
                ],
                '$expectedResult' => true,
                '$error' => new ExactTargetClientException('InvalidEmailAddress: random@customer.com')
            ],

            // ------------------------------
        ];
    }

    /**
     * @dataProvider sendMethodDataProvider
     */
    public function testShouldSendCustomersAndRecoverFromErrors(
        $customers,
        $dataExtension,
        $expectations,
        $expectedResult = true,
        $error = null
    ) {
        // Arrange
        $exacttarget     = m::mock(Client::class);
        $logger          = m::mock(LoggerInterface::class);
        $customerUpdater = new CustomerUpdater($exacttarget, $logger);
        $test            = $this;

        foreach ($customers as $key => $value) {
            $customers[$key] = new Customer;
            $customers[$key]->fill($value, true);
        }

        // Act
        $exacttarget->shouldReceive('addDataExtensionRow')
            ->once()
            ->andReturnUsing(function ($parameters) use ($test, $expectations, $error, $logger) {
                $test->assertEquals($expectations, $parameters);
                if ($error) {
                    throw $error;
                }
            });

        if ($error) {
            $logger->shouldReceive('error')
                ->once()
                ->andReturnUsing(function ($message) use ($test, $error) {
                    $this->assertTrue(true && strstr($message, $error->getMessage()));
                });
        }

        // Assert
        $this->assertEquals($expectedResult, $customerUpdater->send($customers, $dataExtension));
    }

    public function testShouldThrownExceptionIfCustomersAreNotValid()
    {
        // Arrange
        $exacttarget     = m::mock(Client::class);
        $logger          = m::mock(LoggerInterface::class);
        $customerUpdater = new CustomerUpdater($exacttarget, $logger);
        $customers       = 7; // An integer. lol

        // Act
        $this->setExpectedException(\InvalidArgumentException::class);

        // Assert
        $customerUpdater->send($customers, 'fooBar');
    }
}
