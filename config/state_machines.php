<?php

return [
    'shipment' => [
        'draft' => ['confirmed', 'cancelled'],
        'confirmed' => ['allocated', 'cancelled', 'corrected'],
        'allocated' => ['picked'],
        'picked' => ['shipped'],
        'shipped' => ['billed', 'corrected'],
        'billed' => ['closed', 'corrected'],
        'closed' => [],
        'cancelled' => [],
        'corrected' => [],
    ],

    'invoice' => [
        'draft' => ['confirmed', 'cancelled'],
        'confirmed' => ['issued', 'cancelled'],
        'issued' => ['closed'],
        'closed' => [],
        'cancelled' => [],
    ],

    'payment' => [
        'draft' => ['confirmed', 'cancelled'],
        'confirmed' => ['allocated', 'cancelled'],
        'allocated' => ['closed'],
        'closed' => [],
        'cancelled' => [],
    ],

    'stock_movement' => [
        'draft' => ['confirmed', 'cancelled'],
        'confirmed' => ['closed'],
        'closed' => [],
        'cancelled' => [],
    ],

    'monthly_closing' => [
        'draft' => ['calculated', 'cancelled'],
        'calculated' => ['confirmed', 'cancelled'],
        'confirmed' => ['closed'],
        'closed' => [],
        'cancelled' => [],
    ],
];

