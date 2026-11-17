<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WeArePixel\Inlay\Http\Controllers\InlayAssetController;
use WeArePixel\Inlay\Http\Controllers\PagePreviewController;
use WeArePixel\Inlay\Livewire\PageCreate;
use WeArePixel\Inlay\Livewire\PageEditor;
use WeArePixel\Inlay\Livewire\PageIndex;
use WeArePixel\Inlay\Livewire\SiteSettingsEditor;

// A bare visit to the admin's own prefix root (e.g. /inlay) has nowhere
// else obvious to go - redirect to the page index rather than 404ing.
// Named absolutely (not relatively) since it targets a route outside this
// file's own "pages." namespace.
Route::get('/', fn () => redirect()->route('inlay.admin.pages.index'))->name('index');

Route::get('/assets/inlay.css', [InlayAssetController::class, 'css'])->name('assets.css');
Route::get('/assets/inlay.js', [InlayAssetController::class, 'js'])->name('assets.js');

Route::get('/pages', PageIndex::class)->name('pages.index');
Route::get('/pages/create', PageCreate::class)->name('pages.create');
Route::get('/pages/{page}/edit', PageEditor::class)->name('pages.edit');
Route::get('/pages/{page}/preview', PagePreviewController::class)->name('pages.preview');
Route::get('/settings/{group?}', SiteSettingsEditor::class)->name('settings');
