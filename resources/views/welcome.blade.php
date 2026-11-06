@extends('layouts.guest')

@section('lang', 'id')
@section('title', 'FreeDOMS | Digital Preventive Maintenance')

@section('content')

<div class="landing">

    <x-landing.navbar />

    <main>

        <x-landing.hero />

        <x-landing.social-proof />

        <x-landing.problem-solution />

        <x-landing.features />

        <x-landing.how-it-works />

        <x-landing.qr-demo />

        <x-landing.statistics />

        <x-landing.testimonial />

        <x-landing.final-cta />

    </main>

    <x-landing.footer />

</div>

@endsection
