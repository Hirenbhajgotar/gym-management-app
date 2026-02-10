<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $title }} - {{ $gym->name }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        body { 
            font-family: 'Inter', sans-serif; 
            color: #1f2937; 
            line-height: 1.6;
            margin: 0;
            padding: 0;
            background: #ffffff;
        }
        
        .header { 
            background: linear-gradient(135deg, {{ $brandColors['primary'] ?? '#2563eb' }} 0%, {{ $brandColors['secondary'] ?? '#10b981' }} 100%);
            color: white;
            padding: 10px 0;
            position: relative;
            overflow: hidden;
        }
        
        .header-pattern {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: repeating-linear-gradient(
                45deg,
                rgba(255,255,255,0.05),
                rgba(255,255,255,0.05) 10px,
                rgba(255,255,255,0) 10px,
                rgba(255,255,255,0) 20px
            );
        }
        
        .header-content {
            position: relative;
            z-index: 1;
            text-align: center;
        }
        
        .logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 5px;
        }
        
        .logo-img {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            object-fit: cover;
            margin-right: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            background: white;
            padding: 6px;
        }
        
        .logo-text {
            font-size: 24px; 
            font-weight: 700; 
            color: white;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .meta { 
            font-size: 13px; 
            color: rgba(255, 255, 255, 0.9); 
            margin-top: 4px;
            font-weight: 500;
        }
        
        .section { 
            margin-bottom: 35px; 
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .section-title { 
            font-size: 18px; 
            font-weight: 600; 
            background: #f8fafc;
            border-bottom: 2px solid {{ $brandColors['primary'] ?? '#2563eb' }}; 
            padding: 16px 24px; 
            margin-bottom: 0;
            color: #1f2937;
        }
        
        .section-content {
            padding: 24px;
        }
        
        table { 
            width: 100%; 
            border-collapse: collapse; 
            font-size: 13px;
        }
        
        th, td { 
            padding: 12px 16px; 
            border-bottom: 1px solid #e5e7eb; 
            text-align: left;
        }
        
        th { 
            background-color: #f9fafb; 
            font-weight: 600; 
            color: #374151;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        tr:hover {
            background-color: #f9fafb;
        }
        
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        
        .text-success { color: {{ $brandColors['success'] ?? '#22c55e' }}; font-weight: 600; }
        .text-danger { color: {{ $brandColors['danger'] ?? '#ef4444' }}; font-weight: 600; }
        .text-warning { color: {{ $brandColors['warning'] ?? '#f59e0b' }}; font-weight: 600; }
        .text-info { color: {{ $brandColors['info'] ?? '#3b82f6' }}; font-weight: 600; }
        
        .badge { 
            padding: 4px 12px; 
            border-radius: 4px; 
            font-size: 11px; 
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-success { 
            background: {{ $brandColors['success'] ?? '#22c55e' }}20; 
            color: {{ $brandColors['success'] ?? '#22c55e' }}; 
        }
        
        .badge-danger { 
            background: {{ $brandColors['danger'] ?? '#ef4444' }}20; 
            color: {{ $brandColors['danger'] ?? '#ef4444' }}; 
        }
        
        .badge-warning { 
            background: {{ $brandColors['warning'] ?? '#f59e0b' }}20; 
            color: {{ $brandColors['warning'] ?? '#f59e0b' }}; 
        }
        
        .chart-container {
            margin: 20px 0;
            text-align: center;
        }
        
        .chart-image {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .chart-title {
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .stat-card {
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid {{ $brandColors['primary'] ?? '#2563eb' }};
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 4px;
        }
        
        .stat-label {
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .footer {
            margin-top: 50px; 
            text-align: center; 
            font-size: 11px; 
            color: #9ca3af;
            padding: 20px;
            border-top: 1px solid #e5e7eb;
        }
        
        .page-break {
            page-break-before: always;
        }
        
        .no-break {
            page-break-inside: avoid;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-pattern"></div>
        <div class="header-content">
            <div class="logo-container">
                @if($gymLogo)
                    <img src="{{ $gymLogo }}" alt="{{ $gym->name }}" class="logo-img">
                @else
                    <div class="logo-img" style="display: flex; align-items: center; justify-content: center; background: {{ $brandColors['primary'] ?? '#2563eb' }}; color: white; font-size: 24px; font-weight: bold;">
                        {{ substr($gym->name, 0, 1) }}
                    </div>
                @endif
                <div class="logo-text">{{ $gym->name }}</div>
            </div>
            <div class="meta">{{ $title }} • Generated on {{ $date }}</div>
            @if(isset($report->filters) && !empty($report->filters))
                <div class="meta" style="margin-top: 4px; font-size: 12px;">
                    Filters: {{ implode(', ', array_keys($report->filters)) }}
                </div>
            @endif
        </div>
    </div>

    @yield('content')
    
    <div class="footer">
        <div>Generated by {{ config('app.name') }} • {{ now()->format('Y-m-d H:i:s') }}</div>
        <div style="margin-top: 4px;">© {{ date('Y') }} {{ $gym->name }}. All rights reserved.</div>
    </div>
</body>
</html>
