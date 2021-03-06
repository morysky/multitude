<?php

namespace Leadgen\Customer;

use Leadgen\Interaction\Interaction;
use Mockery as m;
use MongoDB\BSON\UTCDateTime;
use Mongolid\Cursor\EmbeddedCursor;
use Mongolid\DataMapper\DataMapper;
use PHPUnit_Framework_TestCase;

class InteractionsParserTest extends PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        m::close();
        app()->forgetInstance(Repository::class);
    }

    public function interactionParserDataProvider()
    {
        return [
            // ----------------
            'interactions a customer that exists' => [
                '$customersInDatabase' => [
                    [
                        '_id'   => 123,
                        'email' => 'johndoe@example.com',
                    ],
                ],
                '$interactionsToBeParsed' => [
                    ['_id' => 1, 'authorId' => 123, 'interaction' => 'a', 'location' => 'thaplace', 'created_at' => new UTCDateTime(new \DateTime('2016-12-17'))],
                    ['_id' => 2, 'authorId' => 123, 'interaction' => 'b', 'created_at' => new UTCDateTime(new \DateTime('2016-12-17'))],
                    ['_id' => 3, 'authorId' => 123, 'interaction' => 'c', 'created_at' => new UTCDateTime(new \DateTime('2016-12-17'))],
                ],
                '$expectedTouchedCustomers' => [
                    [
                        '_id'          => 123,
                        'email'        => 'johndoe@example.com',
                        'interactions' => [
                            ['_id' => 1, 'authorId' => 123, 'interaction' => 'a', 'location' => 'thaplace', 'created_at' => new UTCDateTime(new \DateTime('2016-12-17'))],
                            ['_id' => 2, 'authorId' => 123, 'interaction' => 'b', 'created_at' => new UTCDateTime(new \DateTime('2016-12-17'))],
                            ['_id' => 3, 'authorId' => 123, 'interaction' => 'c', 'created_at' => new UTCDateTime(new \DateTime('2016-12-17'))],
                        ],
                        'interacted_at' => new UTCDateTime(new \DateTime('2016-12-17')),
                        'location' => 'thaplace'
                    ],
                ],
            ],

            // ----------------
            'interactions a customer that don\'t exists yet' => [
                '$customersInDatabase'    => [],
                '$interactionsToBeParsed' => [
                    ['_id' => 1, 'authorId' => 123, 'interaction' => 'a', 'location' => 'thaplace', 'created_at' => new UTCDateTime(new \DateTime('2016-12-17'))],
                    ['_id' => 2, 'authorId' => 123, 'interaction' => 'b', 'location' => 'thaplace', 'created_at' => new UTCDateTime(new \DateTime('2016-12-17'))],
                    ['_id' => 3, 'authorId' => 123, 'interaction' => 'c', 'location' => 'newplace', 'created_at' => new UTCDateTime(new \DateTime('2016-12-17'))],
                ],
                '$expectedTouchedCustomers' => [
                    [
                        '_id'          => 123,
                        'docNumber'    => null,
                        'interactions' => [
                            ['_id' => 1, 'authorId' => 123, 'interaction' => 'a', 'location' => 'thaplace', 'created_at' => new UTCDateTime(new \DateTime('2016-12-17'))],
                            ['_id' => 2, 'authorId' => 123, 'interaction' => 'b', 'location' => 'thaplace', 'created_at' => new UTCDateTime(new \DateTime('2016-12-17'))],
                            ['_id' => 3, 'authorId' => 123, 'interaction' => 'c', 'location' => 'newplace', 'created_at' => new UTCDateTime(new \DateTime('2016-12-17'))],
                        ],
                        'interacted_at' => new UTCDateTime(new \DateTime('2016-12-17')),
                        'location' => 'newplace'
                    ],
                ],
            ],

            // ----------------
            'mixed interactions' => [
                '$customersInDatabase' => [
                    [
                        '_id'   => 123,
                        'email' => 'johndoe@example.com',
                    ],
                ],
                '$interactionsToBeParsed' => [
                    ['_id' => 1, 'authorId' => 123, 'interaction' => 'a', 'location' => 'thaplace', 'created_at' => new UTCDateTime(new \DateTime('2016-12-17'))],
                    ['_id' => 2, 'authorId' => 123, 'interaction' => 'b', 'location' => 'thaplace', 'created_at' => new UTCDateTime(new \DateTime('2016-12-17'))],
                    ['_id' => 3, 'authorId' => 456, 'interaction' => 'c', 'author' => 'example@zizaco.net', 'created_at' => new UTCDateTime(new \DateTime('2016-12-17'))],
                    ['_id' => 4, 'authorId' => 456, 'interaction' => 'd', 'author' => 'example@zizaco.net', 'created_at' => new UTCDateTime(new \DateTime('2016-12-17'))],
                ],
                '$expectedTouchedCustomers' => [
                    [
                        '_id'          => 123,
                        'email'        => 'johndoe@example.com',
                        'interactions' => [
                            ['_id' => 1, 'authorId' => 123, 'interaction' => 'a', 'location' => 'thaplace', 'created_at' => new UTCDateTime(new \DateTime('2016-12-17'))],
                            ['_id' => 2, 'authorId' => 123, 'interaction' => 'b', 'location' => 'thaplace', 'created_at' => new UTCDateTime(new \DateTime('2016-12-17'))],
                        ],
                        'interacted_at' => new UTCDateTime(new \DateTime('2016-12-17')),
                        'location' => 'thaplace'
                    ],
                    [
                        '_id'          => 456,
                        'email'        => 'example@zizaco.net',
                        'interactions' => [
                            ['_id' => 3, 'authorId' => 456, 'interaction' => 'c', 'author' => 'example@zizaco.net', 'created_at' => new UTCDateTime(new \DateTime('2016-12-17'))],
                            ['_id' => 4, 'authorId' => 456, 'interaction' => 'd', 'author' => 'example@zizaco.net', 'created_at' => new UTCDateTime(new \DateTime('2016-12-17'))],
                        ],
                        'interacted_at' => new UTCDateTime(new \DateTime('2016-12-17')),
                        'location' => null
                    ],
                ],
            ],
            // ----------------
        ];
    }

    /**
     * @dataProvider interactionParserDataProvider
     */
    public function testShouldParseInteractionsAndOutputCustomers(
        $customersInDatabase,
        $interactionsToBeParsed,
        $expectedTouchedCustomers
    ) {
        // Arrange
        $customerRepo = m::mock(Repository::class);
        $interactionParser = new InteractionsParser($customerRepo);
        $dataMapper = m::mock(DataMapper::class);

        $interactions = [];
        $customerIds = [];
        foreach ($interactionsToBeParsed as $key => $entityAttributes) {
            $interactions[$key] = new Interaction();
            $interactions[$key]->fill($entityAttributes);
            $customerIds[] = $entityAttributes['authorId'];
        }

        $customers = [];
        foreach ($customersInDatabase as $key => $entityAttributes) {
            $customers[$key] = m::mock(Customer::class.'[save]');
            $customers[$key]->fill($entityAttributes);
            $customers[$key]->shouldReceive('save')
                ->once();
        }

        // Act
        app()->instance(DataMapper::class, $dataMapper);

        $customerRepo->shouldReceive('where')
            ->once()
            ->with(['_id' => ['$in' => array_values(array_unique($customerIds))]], 1, -1)
            ->andReturn(new EmbeddedCursor(Customer::class, $customers));

        $dataMapper->shouldReceive('first')
            ->andReturnUsing(function ($id) use (&$customers) {
                foreach ($customers as $customer) {
                    if ($id == $customer->_id) {
                        return $customer;
                    }
                }
            });

        $dataMapper->shouldReceive('save')
            ->andReturnUsing(function ($entity) use (&$customers) {
                if (!in_array($entity, $customers)) {
                    $customers[] = $entity;
                }
            });

        // Assert
        $interactionParser->parse($interactions);
        foreach ($customers as $key => $customer) {
            $this->assertEquals($expectedTouchedCustomers[$key], $customer->toArray());
        }
    }
}
