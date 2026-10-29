<?php
namespace App\Exceptions;

class DuplicateCustomerException extends AppException
{
    public function __construct(string $field)
    {
        parent::__construct('A customer already uses this ' . $field . '.');
    }
}
