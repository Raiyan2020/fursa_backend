@extends('dashboard.layout.main')
@section('title', __('site_settings'))
@section('content')
    <form action="{{ route('admin.site-settings.update') }}" method="POST">
        @csrf
        @method('PUT')
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header"><h4 class="card-title">{{ __('social links') }}</h4></div>
                    <div class="card-content"><div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-1">
                                <label>TikTok</label>
                                <input type="url" name="tiktok_url" class="form-control" value="{{ old('tiktok_url', $settings->tiktok_url) }}">
                            </div>
                            <div class="col-md-6 mb-1">
                                <label>X / Twitter</label>
                                <input type="url" name="twitter_url" class="form-control" value="{{ old('twitter_url', $settings->twitter_url) }}">
                            </div>
                            <div class="col-md-6 mb-1">
                                <label>YouTube</label>
                                <input type="url" name="youtube_url" class="form-control" value="{{ old('youtube_url', $settings->youtube_url) }}">
                            </div>
                            <div class="col-md-6 mb-1">
                                <label>Instagram</label>
                                <input type="url" name="instagram_url" class="form-control" value="{{ old('instagram_url', $settings->instagram_url) }}">
                            </div>
                        </div>
                    </div></div>
                </div>
            </div>

            <div class="col-12">
                <div class="card">
                    <div class="card-header"><h4 class="card-title">{{ __('contact us') }}</h4></div>
                    <div class="card-content"><div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-1">
                                <label>{{ __('contact_email') }}</label>
                                <input type="email" name="contact_email" class="form-control" value="{{ old('contact_email', $settings->contact_email) }}">
                            </div>
                            <div class="col-md-6 mb-1">
                                <label>{{ __('contact_phone') }}</label>
                                <input type="text" name="contact_phone" class="form-control" value="{{ old('contact_phone', $settings->contact_phone) }}">
                            </div>
                            <div class="col-md-6 mb-1">
                                <label>{{ __('contact_whatsapp') }}</label>
                                <input type="text" name="contact_whatsapp" class="form-control" value="{{ old('contact_whatsapp', $settings->contact_whatsapp) }}">
                            </div>
                            <div class="col-md-6 mb-1">
                                <label>{{ __('contact_address_en') }}</label>
                                <input type="text" name="contact_address_en" class="form-control" value="{{ old('contact_address_en', $settings->contact_address_en) }}">
                            </div>
                            <div class="col-md-6 mb-1">
                                <label>{{ __('contact_address_ar') }}</label>
                                <input type="text" name="contact_address_ar" class="form-control" value="{{ old('contact_address_ar', $settings->contact_address_ar) }}">
                            </div>
                            <div class="col-md-6 mb-1">
                                <label>{{ __('contact_page_text_en') }}</label>
                                <textarea name="contact_page_text_en" class="form-control" rows="4">{{ old('contact_page_text_en', $settings->contact_page_text_en) }}</textarea>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label>{{ __('contact_page_text_ar') }}</label>
                                <textarea name="contact_page_text_ar" class="form-control" rows="4">{{ old('contact_page_text_ar', $settings->contact_page_text_ar) }}</textarea>
                            </div>
                        </div>
                    </div></div>
                </div>
            </div>

            <div class="col-12">
                <div class="card">
                    <div class="card-header"><h4 class="card-title">{{ __('footer') }}</h4></div>
                    <div class="card-content"><div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-1">
                                <label>{{ __('copyright_en') }}</label>
                                <input type="text" name="copyright_en" class="form-control" value="{{ old('copyright_en', $settings->copyright_en) }}">
                            </div>
                            <div class="col-md-6 mb-1">
                                <label>{{ __('copyright_ar') }}</label>
                                <input type="text" name="copyright_ar" class="form-control" value="{{ old('copyright_ar', $settings->copyright_ar) }}">
                            </div>
                        </div>
                        <div class="mt-1">
                            <button type="submit" class="btn btn-primary">{{ __('save') }}</button>
                        </div>
                    </div></div>
                </div>
            </div>
        </div>
    </form>
@endsection
