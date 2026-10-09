<?php

namespace App\Services;

use App\Interfaces\PaymentGatwayInterface;
use Illuminate\Http\Request;

class ManualPaymentService implements PaymentGatwayInterface
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    
     public function initialize(array $data)
     {

     }
     public function verify(string $reference)
    {

    }
         public function webhook(Request $request)
     {
        
     }
}
