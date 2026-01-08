<!DOCTYPE html>
@php
    $messages = __('messages');
    $fontFamily = $messages['font.family'] ?? "'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif";
    $fontFamilyHeading = $messages['font.family_heading'] ?? $fontFamily;
    $direction = $messages['direction'] ?? 'LTR';
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ strtolower($direction) === 'rtl' ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title inertia>EasyCurse</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazir-font@v30.1.0/dist/font-face.css" rel="stylesheet">
        <link href="https://fonts.cdnfonts.com/css/minecraft-4" rel="stylesheet">
        <style>
            :root {
                --font-family-base: {!! $fontFamily !!};
                --font-family-heading: {!! $fontFamilyHeading !!};
            }
        </style>
        @vite(['resources/js/app.js'])
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
