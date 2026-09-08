<!DOCTYPE html>
<html lang="{{ $is_ar ? 'ar' : 'en' }}" dir="{{ $is_ar ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <title>{{ $full_name }}</title>
    <style>
        body {
            font-family: "Noto Naskh Arabic", "Amiri", "Segoe UI", Tahoma, Arial, sans-serif;
            margin: 0;
            padding: 0;
            color: #222;
        }
        .topbar { background-color: #2c2a6b; height: 10px; width: 100%; }
        .content { padding: 24px 40px; }

        table.identity-table { width: 100%; margin-bottom: 8px; }
        table.identity-table td { vertical-align: middle; padding: 0; }
        .avatar { width: 56px; height: 56px; }
        .identity-details { font-size: 12px; color: #555; line-height: 1.6; padding-{{ $is_ar ? 'right' : 'left' }}: 12px; }

        h1.name {
            text-align: center;
            color: #2c2a6b;
            font-size: 34px;
            font-weight: 700;
            margin: 12px 0 6px;
        }
        .name-rule {
            width: 220px;
            height: 3px;
            background-color: #2c2a6b;
            margin: 0 auto 28px;
        }

        h2.section {
            color: #2c2a6b;
            font-size: 22px;
            font-weight: 700;
            margin: 18px 0 14px;
        }

        table.cards { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin-bottom: 8px; }
        table.cards td {
            width: 33%;
            border-radius: 12px;
            text-align: center;
            padding: 18px 10px;
        }
        .card-hours { border: 2px solid #3b82f6; }
        .card-hours .value, .card-hours .label { color: #3b82f6; }
        .card-opportunities { border: 2px solid #6d28d9; }
        .card-opportunities .value, .card-opportunities .label { color: #6d28d9; }
        .card-certificates { border: 2px solid #14b8a6; }
        .card-certificates .value, .card-certificates .label { color: #14b8a6; }
        .value { font-size: 26px; font-weight: 700; margin-bottom: 4px; }
        .label { font-size: 13px; font-weight: 700; }

        .divider { height: 2px; background-color: #2c2a6b; margin: 22px 0; }

        table.courses { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.courses th {
            background-color: #f6c9a0;
            text-align: {{ $is_ar ? 'right' : 'left' }};
            padding: 10px 12px;
            font-size: 13px;
            font-weight: 700;
        }
        table.courses td {
            padding: 9px 12px;
            font-size: 13px;
            border-bottom: 1px solid #eee;
        }
        .no-data { padding: 14px 4px; color: #666; font-size: 13px; }

        table.footer-table { width: 100%; }
        table.footer-table td { vertical-align: middle; }
        .qr { width: 70px; height: 70px; }
        .quote { font-style: italic; font-size: 14px; color: #333; text-align: center; }
    </style>
</head>
<body>
    <div class="topbar"></div>
    <div class="content">
        <h1 class="name">{{ $full_name }}</h1>
        <div class="name-rule"></div>

        @if ($email || $phone || $civil_id)
            <table class="identity-table">
                <tr>
                    @if ($profile_pic)
                        <td style="width:56px;"><img class="avatar" src="{{ $profile_pic }}" alt=""></td>
                    @endif
                    <td class="identity-details">
                        @if ($email)<div>{{ $is_ar ? 'البريد الإلكتروني' : 'Email' }}: {{ $email }}</div>@endif
                        @if ($phone)<div>{{ $is_ar ? 'رقم الهاتف' : 'Phone' }}: {{ $phone }}</div>@endif
                        @if ($civil_id)<div>{{ $is_ar ? 'الرقم المدني' : 'Civil ID' }}: {{ $civil_id }}</div>@endif
                    </td>
                </tr>
            </table>
        @endif

        <h2 class="section">{{ $is_ar ? 'الإنجازات' : 'Achievements' }}</h2>
        <table class="cards">
            <tr>
                <td class="card-hours">
                    <div class="value">{{ number_format((float) $total_hours, 2) }}</div>
                    <div class="label">{{ $is_ar ? 'ساعات التطوع' : 'Volunteer hours' }}</div>
                </td>
                <td class="card-opportunities">
                    <div class="value">{{ $total_opportunities }}</div>
                    <div class="label">{{ $is_ar ? 'فرص التطوع' : 'Volunteer Opportunities' }}</div>
                </td>
                <td class="card-certificates">
                    <div class="value">{{ $total_certificates }}</div>
                    <div class="label">{{ $is_ar ? 'الشهادات' : 'Certificates' }}</div>
                </td>
            </tr>
        </table>

        <div class="divider"></div>

        <h2 class="section">{{ $is_ar ? 'الفرص والفعاليات' : 'Opportunities & Events' }}</h2>
        <table class="courses">
            <thead>
                <tr>
                    <th>{{ $is_ar ? 'الرقم' : 'Number' }}</th>
                    <th>{{ $is_ar ? 'الاسم' : 'Name' }}</th>
                    <th>{{ $is_ar ? 'السنة' : 'Year' }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $index => $row)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $is_ar ? ($row['title_ar'] ?: $row['title_en']) : ($row['title_en'] ?: $row['title_ar']) }}</td>
                        <td>{{ $row['year'] ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="no-data">{{ $is_ar ? 'لا توجد بيانات' : 'No data available' }}</td></tr>
                @endforelse
            </tbody>
        </table>

        <table class="footer-table" style="margin-top:60px;">
            <tr>
                <td style="width:80px;">
                    @if ($qr_code_path)
                        <img class="qr" src="{{ $qr_code_path }}" alt="QR">
                    @endif
                </td>
                <td class="quote">"{{ $is_ar ? 'شكراً - لقد أحدثت فرقاً' : 'Thank you - you made the difference.' }}"</td>
                <td style="width:80px;"></td>
            </tr>
        </table>
    </div>
</body>
</html>
