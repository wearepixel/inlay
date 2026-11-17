<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use WeArePixel\Inlay\Support\ImageValue;

it('resolves url() through the configured disk, not a hardcoded one', function () {
    config(['inlay.disk' => 'public']);

    $value = new ImageValue('inlay/photo.png');

    expect($value->url())->toBe(Storage::disk('public')->url('inlay/photo.png'));
});

it('is empty by default and reports that via isEmpty(), not truthiness', function () {
    $value = new ImageValue;

    expect($value->isEmpty())->toBeTrue()
        ->and($value->url())->toBe('')
        ->and((bool) $value)->toBeTrue(); // any object is truthy - isEmpty() is the real check
});

it('is not empty once a path is set', function () {
    $value = new ImageValue('inlay/photo.png');

    expect($value->isEmpty())->toBeFalse();
});

it('casts to string as the resolved url, so echoing it directly in Blade is correct', function () {
    config(['inlay.disk' => 'public']);

    $value = new ImageValue('inlay/photo.png');

    expect((string) $value)->toBe($value->url());
});

it('serves a root-relative path as-is, so a section can default to a bundled image', function () {
    expect((new ImageValue('/images/hero.jpg'))->url())->toBe('/images/hero.jpg');
});

it('does not treat a protocol-relative path as a bundled image', function () {
    expect((new ImageValue('//evil.example/x.png'))->url())->not->toBe('//evil.example/x.png');
});

it('does not treat a backslash path as a bundled image, since browsers resolve it offsite', function () {
    expect((new ImageValue('/\\evil.example/x.png'))->isPublicPath())->toBeFalse();
});
