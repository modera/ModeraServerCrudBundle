<?php

// create

$createActionRequestPayload = [
    'hydration' => [
        'profile' => 'new_record',
    ],
    'record' => [
        'title' => 'Some title',
        'body' => 'Some text goes here',
    ],
];

$successfulCreateActionResponse = [
    'success' => true,
    'created_models' => [
        'article',
    ],
    'result' => [
        'id' => 1,
        'title' => 'Some title',
        'body' => 'Some text goes here',
    ],
];

$validationFailedCreateActionResponse = [
    'success' => false,
    'field_errors' => [
        'title' => [
            'Title is too short',
        ],
    ],
    'general_errors' => [
        'Admin has disabled functionality of adding new articles',
    ],
];

// list

$listActionRequestPayload = [
    'hydration' => [
        'profile' => 'list',
    ],
    'filter' => [
        [
            'property' => 'category',
            'value' => 'eq:1',
        ],
        [
            'property' => 'isPublished',
            'value' => 'eq:true',
        ],
    ],
    'fetch' => [
        'author',
    ],
    'limit' => 25,
];

$listActionResponse = [
    'success' => true,
    'items' => [
        [
            'title' => 'Some title',
            'body' => 'Some text goes here',
        ],
    ],
];

// misc

$exceptionResponseInDev = [
    'success' => false,
    'exception_class' => 'RuntimeException',
    'stack_trace' => [
        '-',
        '--',
        '---',
    ],
    'file' => 'FooController.php',
    'message' => 'Something went terribly wrong',
    'code' => '123',
    'line' => '123',
];

$exceptionResponseInProd = [
    'success' => false,
    'message' => 'Some preconfigured default message',
];
