<?php

namespace App\View\Components;

use Illuminate\View\Component;

class CustomSelect extends Component
{
    public $options;
    public $name;
    public $id;
    public $placeholder;

    public function __construct($options, $name, $id, $placeholder = null)
    {
        $this->options = $options;
        $this->name = $name;
        $this->id = $id;
        $this->placeholder = $placeholder;
    }


    public function render()
    {
        return view('components.custom-select');
    }
}
