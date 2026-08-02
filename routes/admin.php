<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WeArePixel\Inlay\Http\Controllers\InlayAssetController;
use WeArePixel\Inlay\Http\Controllers\PagePreviewController;
use WeArePixel\Inlay\Livewire\PageCreate;
use WeArePixel\Inlay\Livewire\PageEditor;
use WeArePixel\Inlay\Livewire\PageIndex;

Route::get('/assets/inlay.css', [InlayAssetController::class, 'css'])->name('assets.css');
Route::get('/assets/inlay.js', [InlayAssetController::class, 'js'])->name('assets.js');

Route::get('/pages', PageIndex::class)->name('pages.index');
Route::get('/pages/create', PageCreate::class)->name('pages.create');
Route::get('/pages/{page}/edit', PageEditor::class)->name('pages.edit');
Route::get('/pages/{page}/preview', PagePreviewController::class)->name('pages.preview');
