<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('The uploaded file is too large. Please upload an image smaller than 10 MB.') }}</title>
    <style>
        body { font-family: sans-serif; padding: 48px 24px; text-align: center; color: #1f2937; }
        a { color: #7c3aed; }
    </style>
</head>
<body>
    <h2>{{ __('The uploaded file is too large. Please upload an image smaller than 10 MB.') }}</h2>
    <p><a href="javascript:history.back()">{{ __('back') }}</a></p>
</body>
</html>
