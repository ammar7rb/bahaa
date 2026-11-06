<?php

namespace App\Exceptions;

use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/** Preserve the validation response while rolling back the enclosing purchase. */
class VendorPublicationRollback extends RuntimeException
{
    public function __construct(public readonly Response $response) { parent::__construct('Product was not created'); }
}
