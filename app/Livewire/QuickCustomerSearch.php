<?php

namespace App\Livewire;

use App\Models\Customer;
use Livewire\Component;

class QuickCustomerSearch extends Component
{
    public string $query = '';

    public function render()
    {
        $customers = mb_strlen(trim($this->query)) >= 2
            ? Customer::with('tier')->search($this->query)->limit(6)->get()
            : collect();

        return view('livewire.quick-customer-search', compact('customers'));
    }
}
