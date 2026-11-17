<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Section;
use WeArePixel\Inlay\Support\LinkValue;

section(
    Section::make('pricing-compare')
        ->name('Pricing with comparison')
        ->category('Conversion')
        ->description('Three pricing plans side by side, each with a name, price, billing period, short description, a list of key features and a button, with one plan optionally highlighted as most popular. Below them, a feature comparison table with a row per feature and a column per plan, where "yes" and "no" show as a tick or a dash and anything else shows as text. Only use prices the business has confirmed.')
        ->keywords(['pricing', 'plans', 'tiers', 'compare', 'comparison table', 'feature matrix', 'packages', 'subscription'])
        ->settings(
            Settings::make()
                ->tab('Plans', fn (Settings $t) => $t
                    ->text('eyebrow')->label('Eyebrow')->maxLength(60)->default('Pricing')
                    ->text('heading')->label('Heading')->maxLength(90)->default('Plans that grow with your team')
                    ->textarea('intro')->label('Intro')->maxLength(300)->default('Start free for 14 days on any plan. No credit card required, and you can change or cancel at any time.')
                    ->repeater('plans', Settings::make()
                        ->text('name')->label('Plan name')->required()->maxLength(40)
                        ->text('price')->label('Price')->required()->help('Include the currency, e.g. $29.')->maxLength(20)
                        ->text('period')->label('Billing period')->help('e.g. per user / month')->maxLength(30)
                        ->textarea('description')->label('Description')->maxLength(200)
                        ->textarea('features')->label('Key features')->help('One per line.')
                        ->link('cta')->label('Button')
                        ->toggle('highlighted')->label('Most popular'))
                    ->label('Plans')->help('The comparison table has one column per plan, in this order, for the first three plans.')->default([
                        ['name' => 'Starter', 'price' => '$0', 'period' => 'free forever', 'description' => 'For freelancers and small teams finding their rhythm.', 'features' => "Up to 3 projects\nUnlimited tasks\nShared team board\nMobile apps", 'cta' => new LinkValue('Get started', '#'), 'highlighted' => false],
                        ['name' => 'Team', 'price' => '$12', 'period' => 'per user / month', 'description' => 'For growing teams running client work every week.', 'features' => "Unlimited projects\nClient portal\nTime tracking and invoicing\nAutomations", 'cta' => new LinkValue('Start free trial', '#'), 'highlighted' => true],
                        ['name' => 'Business', 'price' => '$24', 'period' => 'per user / month', 'description' => 'For larger organisations that need control and compliance.', 'features' => "Everything in Team\nSSO and SCIM\nAdvanced permissions\nPriority support", 'cta' => new LinkValue('Talk to sales', '#'), 'highlighted' => false],
                    ]))
                ->tab('Comparison', fn (Settings $t) => $t
                    ->text('compareHeading')->label('Table heading')->maxLength(90)->default('Compare every feature')
                    ->repeater('rows', Settings::make()
                        ->text('feature')->label('Feature')->required()->maxLength(80)
                        ->text('tierOne')->label('First plan')->help('Type yes or no for a tick or a dash, or any short text.')->maxLength(40)
                        ->text('tierTwo')->label('Second plan')->help('Type yes or no for a tick or a dash, or any short text.')->maxLength(40)
                        ->text('tierThree')->label('Third plan')->help('Type yes or no for a tick or a dash, or any short text.')->maxLength(40))
                    ->label('Rows')->default([
                        ['feature' => 'Projects', 'tierOne' => '3', 'tierTwo' => 'Unlimited', 'tierThree' => 'Unlimited'],
                        ['feature' => 'Team members', 'tierOne' => 'Up to 5', 'tierTwo' => 'Unlimited', 'tierThree' => 'Unlimited'],
                        ['feature' => 'File storage', 'tierOne' => '2 GB', 'tierTwo' => '100 GB per user', 'tierThree' => '1 TB per user'],
                        ['feature' => 'Timeline and workload views', 'tierOne' => 'yes', 'tierTwo' => 'yes', 'tierThree' => 'yes'],
                        ['feature' => 'Client portal', 'tierOne' => 'no', 'tierTwo' => 'yes', 'tierThree' => 'yes'],
                        ['feature' => 'Time tracking and invoicing', 'tierOne' => 'no', 'tierTwo' => 'yes', 'tierThree' => 'yes'],
                        ['feature' => 'Automations', 'tierOne' => 'no', 'tierTwo' => '1,000 runs / month', 'tierThree' => 'Unlimited'],
                        ['feature' => 'SSO and SCIM provisioning', 'tierOne' => 'no', 'tierTwo' => 'no', 'tierThree' => 'yes'],
                        ['feature' => 'Audit log', 'tierOne' => 'no', 'tierTwo' => 'no', 'tierThree' => 'yes'],
                        ['feature' => 'Support', 'tierOne' => 'Community', 'tierTwo' => 'Email', 'tierThree' => 'Priority, 4-hour response'],
                    ]))
        )
);
?>
@php
    $plans = array_slice(array_values(array_filter($plans, fn (array $plan) => ($plan['name'] ?? '') !== '')), 0, 3);
    $rows = array_values(array_filter($rows, fn (array $row) => ($row['feature'] ?? '') !== ''));
    $tierKeys = array_slice(['tierOne', 'tierTwo', 'tierThree'], 0, count($plans));
@endphp
<section class="bg-white py-20 sm:py-28">
    <div class="mx-auto max-w-7xl px-6">
        <div class="mx-auto max-w-2xl text-center">
            @if ($eyebrow !== '')
                <p class="text-sm font-semibold uppercase tracking-wider text-indigo-600">{{ $eyebrow }}</p>
            @endif
            @if ($heading !== '')
                <h2 class="mt-2 text-3xl font-bold tracking-tight text-gray-900 text-balance sm:text-4xl">{{ $heading }}</h2>
            @endif
            @if ($intro !== '')
                <p class="mt-4 text-lg text-gray-600 text-pretty">{{ $intro }}</p>
            @endif
        </div>
        @if ($plans !== [])
            <ul class="mx-auto mt-14 grid max-w-md gap-8 lg:max-w-none lg:grid-flow-col lg:auto-cols-fr">
                @foreach ($plans as $plan)
                    @php
                        $highlighted = (bool) ($plan['highlighted'] ?? false);
                        $cta = $plan['cta'] ?? null;
                        $features = array_values(array_filter(array_map('trim', explode("\n", (string) ($plan['features'] ?? '')))));
                    @endphp
                    <li class="relative flex flex-col rounded-2xl bg-white p-8 {{ $highlighted ? 'shadow-xl ring-2 ring-indigo-600' : 'ring-1 ring-gray-200' }}">
                        <div class="flex items-center justify-between gap-4">
                            <h3 class="text-lg font-semibold {{ $highlighted ? 'text-indigo-600' : 'text-gray-900' }}">{{ $plan['name'] }}</h3>
                            @if ($highlighted)
                                <p class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 ring-1 ring-indigo-600/20 ring-inset">Most popular</p>
                            @endif
                        </div>
                        @if (($plan['description'] ?? '') !== '')
                            <p class="mt-3 text-sm text-gray-600 text-pretty">{{ $plan['description'] }}</p>
                        @endif
                        <p class="mt-6 flex items-baseline gap-x-1">
                            <span class="text-4xl font-bold tracking-tight text-gray-900">{{ $plan['price'] ?? '' }}</span>
                            @if (($plan['period'] ?? '') !== '')
                                <span class="text-sm text-gray-600">{{ $plan['period'] }}</span>
                            @endif
                        </p>
                        @if ($features !== [])
                            <ul class="mt-8 flex-1 space-y-3 text-sm text-gray-700">
                                @foreach ($features as $feature)
                                    <li class="flex gap-3">
                                        <svg class="h-5 w-4 shrink-0 text-indigo-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/></svg>
                                        {{ $feature }}
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <div class="flex-1"></div>
                        @endif
                        @if ($cta instanceof \WeArePixel\Inlay\Support\LinkValue && $cta->url !== '')
                            <a href="{{ $cta->url }}" @if ($cta->openInNewTab) target="_blank" rel="noopener" @endif class="mt-8 rounded-md px-4 py-2.5 text-center text-sm font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 {{ $highlighted ? 'bg-indigo-600 text-white shadow-sm hover:bg-indigo-500' : 'text-indigo-600 ring-1 ring-indigo-200 hover:ring-indigo-300 hover:bg-indigo-50' }}">{{ $cta->label }}</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
        @if ($plans !== [] && $rows !== [])
            <div class="mx-auto mt-20 max-w-5xl">
                @if ($compareHeading !== '')
                    <h3 id="pricing-compare-heading" class="text-center text-2xl font-bold tracking-tight text-gray-900 text-balance">{{ $compareHeading }}</h3>
                @endif
                <div class="mt-8 overflow-x-auto rounded-2xl ring-1 ring-gray-200">
                    <table class="w-full min-w-xl border-collapse text-left text-sm" @if ($compareHeading !== '') aria-labelledby="pricing-compare-heading" @endif>
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-4 font-semibold text-gray-900"><span class="sr-only">Feature</span></th>
                                @foreach ($plans as $plan)
                                    <th scope="col" class="px-6 py-4 text-center font-semibold {{ ($plan['highlighted'] ?? false) ? 'text-indigo-600' : 'text-gray-900' }}">{{ $plan['name'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach ($rows as $row)
                                <tr>
                                    <th scope="row" class="px-6 py-4 font-medium text-gray-900">{{ $row['feature'] }}</th>
                                    @foreach ($tierKeys as $index => $tierKey)
                                        @php($value = trim((string) ($row[$tierKey] ?? '')))
                                        <td class="px-6 py-4 text-center text-gray-600 {{ ($plans[$index]['highlighted'] ?? false) ? 'bg-indigo-50/40' : '' }}">
                                            @if (strcasecmp($value, 'yes') === 0)
                                                <svg class="mx-auto size-5 text-indigo-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/></svg>
                                                <span class="sr-only">Included in {{ $plans[$index]['name'] }}</span>
                                            @elseif (strcasecmp($value, 'no') === 0 || $value === '')
                                                <svg class="mx-auto size-5 text-gray-300" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M4 10a.75.75 0 0 1 .75-.75h10.5a.75.75 0 0 1 0 1.5H4.75A.75.75 0 0 1 4 10Z" clip-rule="evenodd"/></svg>
                                                <span class="sr-only">Not included in {{ $plans[$index]['name'] }}</span>
                                            @else
                                                {{ $value }}
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</section>
