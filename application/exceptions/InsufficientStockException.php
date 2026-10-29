<?php
namespace App\Exceptions;

class InsufficientStockException extends AppException
{
    public function __construct(string $productName)
    {
        parent::__construct('Insufficient stock for: ' . $productName);
    }
}
