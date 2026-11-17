@extends('layouts.app')

@section('content')
    <section id="hero" class="bg-white py-24">
        <div class="mx-auto max-w-5xl">
            <h1 class="text-5xl font-bold">We help teams ship faster</h1>
            <p class="mt-6 text-lg">Our tools take care of the busywork.</p>
            <a href="/contact" class="btn">Talk to us</a>
            <img src="/images/team.jpg" alt="The team at work" class="mt-10 rounded-xl">
        </div>
    </section>

    <section class="values py-24">
        <h2>Our values</h2>
        <p>We are <strong>honest</strong> and <a href="/about/values">open</a>.</p>
        <p>Signed up: {{ $count }} people</p>
    </section>
@endsection
