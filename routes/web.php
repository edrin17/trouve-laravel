<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/inventory');

Route::livewire('/inventory', 'inventory.tree')->name('inventory');
