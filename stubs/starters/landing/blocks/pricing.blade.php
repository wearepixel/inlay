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
<section class="bg-white py-20">
    <div class="mx-auto max-w-7xl px-6">
        <div class="mx-auto max-w-2xl text-center">
            <h2 class="text-3xl font-bold tracking-tight text-gray-900 text-balance sm:text-4xl">{{ $heading }}</h2>
            @if ($intro !== '')
                <p class="mt-4 text-lg text-gray-600 text-pretty">{{ $intro }}</p>
            @endif
        </div>
        <div class="mt-14 grid gap-8 lg:grid-flow-col lg:auto-cols-fr">
            @foreach ($plans as $plan)
                @php($highlighted = (bool) ($plan['highlighted'] ?? false))
                <div class="flex flex-col rounded-2xl p-8 {{ $highlighted ? 'bg-gray-950 text-white ring-2 ring-indigo-600' : 'bg-white text-gray-900 ring-1 ring-gray-200' }}">
                    <h3 class="text-lg font-semibold">{{ $plan['name'] ?? '' }}</h3>
                    @if (($plan['description'] ?? '') !== '')
                        <p class="mt-2 text-sm opacity-70">{{ $plan['description'] }}</p>
                    @endif
                    <p class="mt-6 flex items-baseline gap-x-1">
                        <span class="text-4xl font-bold tracking-tight">{{ $plan['price'] ?? '' }}</span>
                        @if (($plan['period'] ?? '') !== '')
                            <span class="text-sm opacity-70">{{ $plan['period'] }}</span>
                        @endif
                    </p>
                    <ul class="mt-8 flex-1 space-y-3 text-sm">
                        @foreach (array_filter(array_map('trim', explode("\n", (string) ($plan['features'] ?? '')))) as $feature)
                            <li class="flex gap-3"><span aria-hidden="true" class="text-indigo-500">&#10003;</span>{{ $feature }}</li>
                        @endforeach
                    </ul>
                    @if (($plan['cta'] ?? null)?->url)
                        <a href="{{ $plan['cta']->url }}" class="mt-8 block rounded-md px-4 py-2.5 text-center text-sm font-semibold {{ $highlighted ? 'bg-indigo-600 text-white hover:bg-indigo-500' : 'ring-1 ring-indigo-600 text-indigo-600 hover:bg-indigo-50' }}">{{ $plan['cta']->label }}</a>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>
