<?php

namespace DP0\Sanchaya\Livewire;

use Illuminate\Contracts\View\View;

class FileManager extends FileBrowser
{
    public function render(): View
    {
        return view('filament-sanchaya::livewire.file-manager');
    }
}
