<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class SidebarLayout extends Component
{
    public string $title;
    public string $breadcrumb;
    public string $pageTitle;

    public function __construct(
        string $title = 'Jurnal',
        string $breadcrumb = 'Jurnal',
        string $pageTitle = 'Jurnal'
    ) {
        $this->title = $title;
        $this->breadcrumb = $breadcrumb;
        $this->pageTitle = $pageTitle;
    }

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.sidebar-layout');
    }
}
