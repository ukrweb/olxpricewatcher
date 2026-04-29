<?php

declare(strict_types=1);

namespace App\Domain\Listing;

enum ListingStatus: string
{
    case New = 'new';
    case Active = 'active';
    case ParseError = 'parse_error';
    case NotFound = 'not_found';
    case NoPrice = 'no_price';
    case Disabled = 'disabled';
}
