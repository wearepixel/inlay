<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Section;

section(
    Section::make('pricing')
        ->name('Pricing')
        ->category('Conversion')
        ->description('Side-by-side pricing plans (1 to 4), each with a name, price, billing period, short description, a list of included features and a button. One plan can be highlighted as recommended. Only use prices the business has confirmed.')
        ->keywords(['plans', 'prices', 'tiers', 'packages', 'subscription'])
        ->settings(
            Settings::make()
                ->text('heading')->label('Heading')->maxLength(90)->default('Simple, transparent pricing')
                ->textarea('intro')->label('Intro')->maxLength(300)->default('')
                ->repeater('plans', Settings::make()
                    ->text('name')->label('Plan name')->required()->maxLength(40)
                    ->text('price')->label('Price')->required()->help('Include the currency, e.g. $29.')->maxLength(20)
                    ->text('period')->label('Billing period')->help('e.g. per month')->maxLength(30)
                    ->textarea('description')->label('Description')->maxLength(200)
                    ->textarea('features')->label('Included features')->help('One per line.')
                    ->link('cta')->label('Button')
                    ->toggle('highlighted')->label('Recommended'))
                ->label('Plans')->default([])
        )
);
?>
<section class="bg-body py-5">
    <div class="container py-lg-4">
        <div class="row justify-content-center text-center">
            <div class="col-lg-8">
                @if ($heading !== '')
                    <h2 class="display-6 fw-bold">{{ $heading }}</h2>
                @endif
                @if ($intro !== '')
                    <p class="lead text-body-secondary mt-3">{{ $intro }}</p>
                @endif
            </div>
        </div>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-{{ max(1, min(4, count($plans))) }} g-4 mt-2 justify-content-center">
            @foreach ($plans as $plan)
                @php($highlighted = (bool) ($plan['highlighted'] ?? false))
                <div class="col">
                    <div class="card h-100 {{ $highlighted ? 'border-primary border-2 shadow' : '' }}">
                        <div class="card-body d-flex flex-column p-4">
                            <h3 class="h5 card-title fw-semibold">
                                {{ $plan['name'] ?? '' }}
                                @if ($highlighted)
                                    <span class="badge text-bg-primary ms-1 align-middle">Recommended</span>
                                @endif
                            </h3>
                            @if (($plan['description'] ?? '') !== '')
                                <p class="small text-body-secondary">{{ $plan['description'] }}</p>
                            @endif
                            <p class="d-flex align-items-baseline gap-1 mt-3">
                                <span class="display-6 fw-bold">{{ $plan['price'] ?? '' }}</span>
                                @if (($plan['period'] ?? '') !== '')
                                    <span class="small text-body-secondary">{{ $plan['period'] }}</span>
                                @endif
                            </p>
                            <ul class="list-unstyled flex-grow-1 small mt-3">
                                @foreach (array_filter(array_map('trim', explode("\n", (string) ($plan['features'] ?? '')))) as $feature)
                                    <li class="d-flex gap-2 mb-2"><span aria-hidden="true" class="text-primary">&#10003;</span>{{ $feature }}</li>
                                @endforeach
                            </ul>
                            @if (($plan['cta'] ?? null)?->url)
                                <a href="{{ $plan['cta']->url }}" class="btn {{ $highlighted ? 'btn-primary' : 'btn-outline-primary' }} w-100 mt-3">{{ $plan['cta']->label }}</a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
