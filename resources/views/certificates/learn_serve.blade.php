<!DOCTYPE html>
<html lang="{{ $is_arabic_name ? 'ar' : 'en' }}" dir="{{ $is_arabic_name ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <title>{{ $certificate_type ?: 'Certificate' }} — {{ $name }}</title>
    <style>
        /* Arabic-capable stack first so shaping never falls back to a Latin-only face. */
        :root {
            --ink: #2b2b2b;
            --brand: #4b1d6a;
            --gold: #b8912f;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: "Noto Naskh Arabic", "Amiri", "Traditional Arabic", "Segoe UI", Tahoma, Arial, sans-serif;
            color: var(--ink);
            background: #f4f4f6;
            padding: 24px;
        }
        .sheet {
            width: 1000px;
            max-width: 100%;
            min-height: 700px;
            margin: 0 auto;
            background: #fff center/cover no-repeat;
            border: 10px solid var(--brand);
            outline: 2px solid var(--gold);
            outline-offset: -20px;
            padding: 64px 72px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            text-align: center;
        }
        .brand { color: var(--brand); font-size: 34px; font-weight: 700; letter-spacing: 1px; }
        .kind { margin-top: 6px; color: var(--gold); font-size: 20px; font-weight: 700; }
        .lead { margin-top: 44px; font-size: 18px; color: #555; }
        .name {
            margin: 14px 0 6px;
            font-size: 46px;
            font-weight: 700;
            color: var(--brand);
            /* Long Arabic names must not clip or reflow oddly. */
            line-height: 1.4;
            word-break: keep-all;
        }
        .civil { font-size: 15px; color: #777; }
        .course { margin-top: 30px; font-size: 22px; font-weight: 700; }
        .course small { display: block; font-size: 16px; font-weight: 400; color: #666; margin-top: 4px; }
        .meta { margin-top: 34px; font-size: 15px; color: #555; line-height: 1.9; }
        .foot {
            margin-top: 48px;
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            color: #888;
        }
        @media print {
            body { background: #fff; padding: 0; }
            .sheet { border-width: 8px; box-shadow: none; }
            @page { size: A4 landscape; margin: 8mm; }
        }
    </style>
</head>
<body>
    <div class="sheet" @if ($background_url) style="background-image:url('{{ $background_url }}')" @endif>
        <div class="brand">فرصة · Forsa</div>
        @if ($certificate_type)
            <div class="kind">{{ $certificate_type }}</div>
        @endif

        <div class="lead">{{ $is_arabic_name ? 'تشهد منصة فرصة بأن' : 'This is to certify that' }}</div>
        <div class="name">{{ $name }}</div>
        @if ($civil_id)
            <div class="civil">{{ $is_arabic_name ? 'الرقم المدني' : 'Civil ID' }}: {{ $civil_id }}</div>
        @endif

        <div class="course">
            {{ $is_arabic_name ? ($title_ar ?: $title_en) : ($title_en ?: $title_ar) }}
            @if ($learning_type)
                <small>{{ $learning_type }}</small>
            @endif
        </div>

        <div class="meta">
            @if ($start_date || $end_date)
                <div>
                    {{ $is_arabic_name ? 'الفترة' : 'Period' }}:
                    {{ $start_date ?: '—' }} → {{ $end_date ?: '—' }}
                </div>
            @endif
            @if ($organizer_name)
                <div>{{ $is_arabic_name ? 'الجهة المنظمة' : 'Organized by' }}: {{ $organizer_name }}</div>
            @endif
        </div>

        <div class="foot">
            <span>{{ $is_arabic_name ? 'تاريخ الإصدار' : 'Issued' }}: {{ $issued_at }}</span>
            <span>#{{ $registration_id }}</span>
        </div>
    </div>
</body>
</html>
