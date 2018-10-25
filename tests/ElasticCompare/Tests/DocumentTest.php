<?php
declare(strict_types=1);

namespace ElasticCompare\Tests;

use ElasticCompare\Document;
use PHPUnit\Framework\TestCase;

class DocumentTest extends TestCase
{
    /**
     * @dataProvider diffDataProvider
     * @param array $source
     * @param array $target
     * @param array $expectedSourceDiff
     * @param array $expectedTargetDiff
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public function testDiff(array $source, array $target, array $expectedSourceDiff, array $expectedTargetDiff)
    {
        $document = Document::getInstance([
            'a' => 'key'
        ]);

        $diff = $document->diff($source, $target);

        $this->assertEquals($source, $expectedSourceDiff);
        $this->assertEquals($diff, $expectedTargetDiff);
    }

    public function diffDataProvider(): array
    {
        return [
            // Arrays are empty gives an empty diff.
            [
                'source' => [],
                'target' => [],
                'expectedSourceDiff' => [],
                'expectedTargetDiff' => []
            ],
            // Diff strings
            [
                'source' => [
                    'a' => 'A'
                ],
                'target' => [
                    'a' => 'A'
                ],
                'expectedSourceDiff' => [],
                'expectedTargetDiff' => []
            ],
            [
                'source' => [
                    'a' => 'A'
                ],
                'target' => [
                    'b' => 'A'
                ],
                'expectedSourceDiff' => [
                    'a' => 'A'
                ],
                'expectedTargetDiff' => [
                    'b' => 'A'
                ]
            ],
            [
                'source' => [
                    'a' => 'A',
                    'c' => 'C'
                ],
                'target' => [
                    'a' => 'A',
                    'b' => 'A'
                ],
                'expectedSourceDiff' => [
                    'c' => 'C'
                ],
                'expectedTargetDiff' => [
                    'b' => 'A'
                ]
            ],
            // Diff int
            [
                'source' => [
                    'a' => 1
                ],
                'target' => [
                    'a' => 1
                ],
                'expectedSourceDiff' => [],
                'expectedTargetDiff' => []
            ],
            [
                'source' => [
                    'a' => 1
                ],
                'target' => [
                    'b' => 2
                ],
                'expectedSourceDiff' => [
                    'a' => 1
                ],
                'expectedTargetDiff' => [
                    'b' => 2
                ]
            ],
            [
                'source' => [
                    'a' => 1,
                    'c' => 3
                ],
                'target' => [
                    'a' => 1,
                    'b' => 2
                ],
                'expectedSourceDiff' => [
                    'c' => 3
                ],
                'expectedTargetDiff' => [
                    'b' => 2
                ]
            ],
            // Diff float
            [
                'source' => [
                    'a' => 1.0
                ],
                'target' => [
                    'a' => 1.0
                ],
                'expectedSourceDiff' => [],
                'expectedTargetDiff' => []
            ],
            [
                'source' => [
                    'a' => 1.01
                ],
                'target' => [
                    'b' => 2.01
                ],
                'expectedSourceDiff' => [
                    'a' => 1.01
                ],
                'expectedTargetDiff' => [
                    'b' => 2.01
                ]
            ],
            [
                'source' => [
                    'a' => 1.01,
                    'c' => 3.001
                ],
                'target' => [
                    'a' => 1.01,
                    'b' => 2.0
                ],
                'expectedSourceDiff' => [
                    'c' => 3.001
                ],
                'expectedTargetDiff' => [
                    'b' => 2.0
                ]
            ],
            // Diff associated array
            [
                'source' => [
                    'a' => [
                        'a' => 1,
                        'b' => 'B',
                        'c' => 3.001,
                    ]
                ],
                'target' => [
                    'a' => [
                        'a' => 1,
                        'b' => 'B',
                        'c' => 3.001,
                    ]
                ],
                'expectedSourceDiff' => ['a' => []],
                'expectedTargetDiff' => ['a' => []]
            ],
            [
                'source' => [
                    'a' => [
                        'a' => 1,
                        'b' => 'B',
                        'c' => 3.001,
                    ]
                ],
                'target' => [
                    'a' => [
                        'd' => 4,
                        'e' => 'E',
                        'f' => 6.001,
                    ]
                ],
                'expectedSourceDiff' => [
                    'a' => [
                        'a' => 1,
                        'b' => 'B',
                        'c' => 3.001,
                    ]
                ],
                'expectedTargetDiff' => [
                    'a' => [
                        'd' => 4,
                        'e' => 'E',
                        'f' => 6.001,
                    ]
                ]
            ],
            [
                'source' => [
                    'a' => [
                        'a' => 1,
                        'b' => 'B',
                        'c' => 3.001,
                    ]
                ],
                'target' => [
                    'b' => [
                        'd' => 4,
                        'e' => 'E',
                        'f' => 6.001,
                    ]
                ],
                'expectedSourceDiff' => [
                    'a' => [
                        'a' => 1,
                        'b' => 'B',
                        'c' => 3.001,
                    ]
                ],
                'expectedTargetDiff' => [
                    'b' => [
                        'd' => 4,
                        'e' => 'E',
                        'f' => 6.001,
                    ]
                ]
            ],
            [
                'source' => [
                    'a' => [
                        'a' => 1,
                        'b' => 'B',
                        'c' => 3.001,
                    ]
                ],
                'target' => [
                    'a' => [
                        'b' => 'B',
                        'e' => 'E',
                        'a' => 6.001,
                    ]
                ],
                'expectedSourceDiff' => [
                    'a' => [
                        'a' => 1,
                        'c' => 3.001,
                    ]
                ],
                'expectedTargetDiff' => [
                    'a' => [
                        'e' => 'E',
                        'a' => 6.001,
                    ]
                ]
            ],
            // Diff nested associated array
            [
                'source' => [
                    'a' => [
                        'a' => []
                    ]
                ],
                'target' => [
                    'a' => [
                        'a' => []
                    ]
                ],
                'expectedSourceDiff' => [
                    'a' => [
                        'a' => []
                    ]
                ],
                'expectedTargetDiff' => [
                    'a' => [
                        'a' => []
                    ]
                ]
            ],
            [
                'source' => [
                    'a' => [
                        'a' => [
                            'a' => 1,
                            'b' => 'B',
                            'c' => 3.001,
                        ]
                    ]
                ],
                'target' => [
                    'a' => [
                        'a' => [
                            'a' => 1,
                            'b' => 'B',
                            'c' => 3.001,
                        ]
                    ]
                ],
                'expectedSourceDiff' => [
                    'a' => [
                        'a' => []
                    ]
                ],
                'expectedTargetDiff' => [
                    'a' => [
                        'a' => []
                    ]
                ]
            ],
            [
                'source' => [
                    'a' => [
                        'a' => [
                            'a' => 1
                        ]
                    ]
                ],
                'target' => [
                    'a' => [
                        'a' => [
                            'a' => 6
                        ]
                    ]
                ],
                'expectedSourceDiff' => [
                    'a' => [
                        'a' => [
                            'a' => 1
                        ]
                    ]
                ],
                'expectedTargetDiff' => [
                    'a' => [
                        'a' => [
                            'a' => 6
                        ]
                    ]
                ]
            ],
            [
                'source' => [
                    'a' => [
                        'a' => [
                            'a' => 1
                        ]
                    ]
                ],
                'target' => [
                    'b' => [
                        'a' => [
                            'c' => 6
                        ]
                    ]
                ],
                'expectedSourceDiff' => [
                    'a' => [
                        'a' => [
                            'a' => 1
                        ]
                    ]
                ],
                'expectedTargetDiff' => [
                    'b' => [
                        'a' => [
                            'c' => 6
                        ]
                    ]
                ]
            ],
            // Diff sequential array
            [
                'source' => [
                    'a' => [
                        'A',
                        'B',
                        'C'
                    ]
                ],
                'target' => [
                    'a' => [
                        'A',
                        'C',
                        'B'
                    ]
                ],
                'expectedSourceDiff' => ['a' => []],
                'expectedTargetDiff' => ['a' => []]
            ],
            [
                'source' => [
                    'a' => [
                        'A',
                        'B',
                        'D'
                    ]
                ],
                'target' => [
                    'a' => [
                        'A',
                        'C',
                        'B'
                    ]
                ],
                'expectedSourceDiff' => [
                    'a' => [
                        'D'
                    ]
                ],
                'expectedTargetDiff' => [
                    'a' => [
                        'C'
                    ]
                ]
            ],
            // Diff sequential array with array's
            [
                'source' => [
                    'a' => [
                        [
                            'key' => 'C',
                            'a' => 'C'
                        ],
                        [
                            'key' => 'A',
                            'a' => 'C'
                        ],
                        [
                            'key' => 'B',
                            'b' => 'B'
                        ]
                    ]
                ],
                'target' => [
                    'a' => [
                        [
                            'key' => 'C',
                            'a' => 'C'
                        ],
                        [
                            'key' => 'B',
                            'b' => 'B'
                        ],
                        [
                            'key' => 'A',
                            'a' => 'C'
                        ]
                    ]
                ],
                'expectedSourceDiff' => ['a' => []],
                'expectedTargetDiff' => ['a' => []]
            ],
            [
                'source' => [
                    'a' => [
                        [
                            'key' => 'C',
                            'a' => 'C'
                        ],
                        [
                            'key' => 'D',
                            'a' => 'C'
                        ],
                        [
                            'key' => 'B',
                            'b' => 'B'
                        ],
                    ]
                ],
                'target' => [
                    'a' => [
                        [
                            'key' => 'C',
                            'a' => 'C'
                        ],
                        [
                            'key' => 'A',
                            'a' => 'C'
                        ],
                        [
                            'key' => 'B',
                            'b' => 'B'
                        ],
                    ]
                ],
                'expectedSourceDiff' => ['a' => [
                    [
                        'key' => 'D',
                        'a' => 'C'
                    ]
                ]],
                'expectedTargetDiff' => ['a' => [
                    [
                        'key' => 'A',
                        'a' => 'C'
                    ]
                ]]
            ],
        ];
    }
}
