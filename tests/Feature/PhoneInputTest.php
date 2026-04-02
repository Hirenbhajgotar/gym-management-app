<?php

namespace Tests\Feature;

use Tests\TestCase;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;

class PhoneInputTest extends TestCase
{
    public function test_phone_format()
    {
        $input = PhoneInput::make('phone');
        $this->assertEquals('E164', $input->getInputNumberFormat());
    }
}
