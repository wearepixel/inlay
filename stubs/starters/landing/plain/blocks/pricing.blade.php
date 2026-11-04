<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Block;

block(
    Block::make('pricing')
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
<section class="inlay-section inlay-pricing">
    <div class="inlay-container">
        @if ($heading !== '' || $intro !== '')
            <div class="inlay-section__header">
                @if ($heading !== '')
                    <h2 class="inlay-heading">{{ $heading }}</h2>
                @endif
                @if ($intro !== '')
                    <p class="inlay-lead">{{ $intro }}</p>
                @endif
            </div>
        @endif
        <div class="inlay-pricing__plans">
            @foreach ($plans as $plan)
                <div class="inlay-card inlay-plan {{ ($plan['highlighted'] ?? false) ? 'inlay-plan--highlighted' : '' }}">
                    <h3 class="inlay-plan__name">{{ $plan['name'] ?? '' }}</h3>
                    @if (($plan['description'] ?? '') !== '')
                        <p class="inlay-muted">{{ $plan['description'] }}</p>
                    @endif
                    <p class="inlay-plan__price"><span>{{ $plan['price'] ?? '' }}</span> @if (($plan['period'] ?? '') !== '')<small>{{ $plan['period'] }}</small>@endif</p>
                    <ul class="inlay-checklist inlay-plan__features">
                        @foreach (array_filter(array_map('trim', explode("\n", (string) ($plan['features'] ?? '')))) as $feature)
                            <li>{{ $feature }}</li>
                        @endforeach
                    </ul>
                    @if (($plan['cta'] ?? null)?->url)
                        <a href="{{ $plan['cta']->url }}" class="inlay-button {{ ($plan['highlighted'] ?? false) ? '' : 'inlay-button--outline' }}">{{ $plan['cta']->label }}</a>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>
