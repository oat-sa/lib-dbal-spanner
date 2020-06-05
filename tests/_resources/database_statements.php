<?php

return [
    'CREATE TABLE statements (
        modelid INT64 NOT NULL,
        subject STRING(255) NOT NULL,
        predicate STRING(255) NOT NULL,
        object STRING(65535),
        l_language STRING(255),
        author STRING(255),
        epoch STRING(255)
    )
    PRIMARY KEY (subject, predicate, object, l_language)',
    'CREATE INDEX k_po ON statements (predicate, object)',
    'CREATE TABLE transactional_test (
        id INT64 NOT NULL,
        consumed BOOL NOT NULL,
        value STRING(255) NOT NULL
    )
    PRIMARY KEY (id)',
];
