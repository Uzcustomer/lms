<?php

namespace App\View\Components;

use Illuminate\View\Component;

class SelectInput extends Component
{
    public $options;
    public $name;
    public $id;

    public function __construct($options, $name, $id)
    {
        $this->options = $options;
        $this->name = $name;
        $this->id = $id;
    }

    public function render()
    {
        return view('components.select-input');
    }
}
