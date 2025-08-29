<?php

namespace App\Livewire\Frontend;

use Livewire\Attributes\Layout;
use Livewire\Component;

class HomeController extends Component
{
    #[Layout('frontend.layouts.app')]
    public function render()
    {
        return view('livewire.frontend.home-controller');
    }
}
