<?php

namespace App\Http\Livewire\Admin;

use Livewire\Component;

class PermissionsCategoryTranslation extends Component
{
    public $name;
    public $translated_ar;
    public $translated_en;
    
    public function render()
    {
        return view('livewire.admin.permissions-category-translation');
    }
}
