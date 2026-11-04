<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WeArePixel\Inlay\Http\Controllers\PreviewLinkController;

Route::get('/{token}', PreviewLinkController::class)
    ->where('token', '[A-Za-z0-9]{40}')
    ->middleware('signed:relative')
    ->name('preview-link');
