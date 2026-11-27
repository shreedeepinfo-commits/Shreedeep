<?php

namespace App\Services\Resellers;

use App\Models\Reseller;
use App\Support\Num;
use Illuminate\Validation\ValidationException;

class InsufficientBalance extends ValidationException
{
    public function __construct(public Reseller $reseller, public int $needed)
    {
        parent::__construct(validator([], []));
        $this->validator->errors()->add('balance', 'Not enough wallet balance: ₹'.Num::indian($needed / 100).' needed, ₹'.Num::indian($reseller->balance / 100).' available. Please recharge.');
    }
}
