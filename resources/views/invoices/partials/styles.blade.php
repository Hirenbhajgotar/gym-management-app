<style>
    @page {
        margin: 24px 30px 24px 30px;
    }
    * {
        box-sizing: border-box;
        font-family: 'DejaVu Sans', sans-serif;
    }
    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 11px;
        color: #1e293b; /* Slate 800 */
        line-height: 1.45;
        margin: 0;
        padding: 0;
        background-color: #ffffff;
    }
    .container {
        width: 100%;
        margin: 0 auto;
    }
    
    /* Layout Helpers */
    .w-half { width: 50%; }
    .w-45 { width: 45%; }
    .w-55 { width: 55%; }
    .align-top { vertical-align: top; }
    .text-right { text-align: right; }
    
    /* Header */
    .header-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 12px;
    }
    .brand-logo-table {
        border-collapse: collapse;
        margin: 0;
        padding: 0;
    }
    .brand-icon-cell {
        vertical-align: middle;
        padding-right: 8px;
        width: 44px;
    }
    .brand-icon-img {
        height: 32px;
        width: auto;
        display: block;
    }
    .brand-name {
        font-size: 21px;
        font-weight: bold;
        font-style: italic;
        letter-spacing: -0.5px;
        line-height: 1;
        text-transform: uppercase;
    }
    .brand-dark { color: #0f172a; }
    .brand-cyan { color: #06b6d4; }
    .brand-tagline {
        font-size: 9px;
        font-weight: bold;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        margin-top: 4px;
    }
    .brand-contact {
        font-size: 9.5px;
        color: #94a3b8;
        margin-top: 2px;
    }
    
    /* Gym Initials Badge */
    .gym-initials-badge {
        width: 34px;
        height: 34px;
        background-color: #0f172a;
        color: #38bdf8;
        border-radius: 6px;
        text-align: center;
        line-height: 34px;
        font-size: 15px;
        font-weight: bold;
    }
    
    /* Invoice Header Details */
    .invoice-badge {
        display: inline-block;
        padding: 3px 8px;
        background-color: #e0f2fe;
        color: #0284c7;
        font-size: 8.5px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1px;
        border-radius: 4px;
        margin-bottom: 3px;
    }
    .invoice-number {
        font-size: 13px;
        font-weight: bold;
        color: #0f172a;
        white-space: nowrap;
        letter-spacing: 0.2px;
        margin: 2px 0 3px 0;
    }
    .invoice-date {
        font-size: 10px;
        color: #64748b;
        margin-bottom: 4px;
    }
    .invoice-date strong {
        color: #0f172a;
        font-weight: bold;
    }
    
    /* Status Badges */
    .badge {
        display: inline-block;
        padding: 2.5px 9px;
        border-radius: 9999px;
        font-size: 8.5px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.8px;
    }
    .badge-completed {
        background-color: #dcfce7;
        color: #15803d;
        border: 1px solid #bbf7d0;
    }
    .badge-pending {
        background-color: #fef3c7;
        color: #b45309;
        border: 1px solid #fde68a;
    }
    .badge-failed {
        background-color: #fee2e2;
        color: #b91c1c;
        border: 1px solid #fecaca;
    }
    
    /* Divider */
    .accent-bar {
        height: 2px;
        background-color: #06b6d4;
        margin-bottom: 16px;
        border-radius: 2px;
    }
    
    /* Info Cards Grid */
    .info-grid-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 10px 0;
        margin-left: -10px;
        margin-right: -10px;
        margin-bottom: 16px;
    }
    .card-box {
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 10px 12px;
        vertical-align: top;
    }
    .card-label {
        font-size: 8.5px;
        font-weight: bold;
        color: #0284c7;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        margin-bottom: 5px;
        border-bottom: 1px solid #e2e8f0;
        padding-bottom: 3px;
    }
    .card-title {
        font-size: 12px;
        font-weight: bold;
        color: #0f172a;
        margin-bottom: 2px;
    }
    .card-text {
        font-size: 10px;
        color: #475569;
        line-height: 1.4;
    }
    .card-row {
        font-size: 10px;
        color: #475569;
        margin-top: 2px;
    }
    .card-row strong {
        color: #334155;
        font-weight: bold;
    }
    
    /* Items Table */
    .items-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 16px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        overflow: hidden;
    }
    .items-table th {
        background-color: #f1f5f9;
        color: #475569;
        font-size: 8.5px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        padding: 7px 10px;
        border-bottom: 1px solid #cbd5e1;
        text-align: left;
    }
    .items-table td {
        padding: 9px 10px;
        border-bottom: 1px solid #e2e8f0;
        color: #334155;
        font-size: 10.5px;
        vertical-align: middle;
    }
    .items-table tr:last-child td {
        border-bottom: none;
    }
    .item-main-title {
        font-weight: bold;
        color: #0f172a;
        font-size: 11.5px;
        margin-bottom: 2px;
    }
    .item-sub-desc {
        font-size: 9.5px;
        color: #64748b;
    }
    .mono-ref {
        font-size: 9.5px;
        color: #475569;
    }
    .item-amount-val {
        font-size: 12px;
        font-weight: bold;
        color: #0f172a;
        text-align: right;
        white-space: nowrap;
    }
    
    /* Summary & Notes Section */
    .summary-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 16px;
    }
    .note-box {
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-left: 3px solid #06b6d4;
        border-radius: 4px;
        padding: 8px 10px;
        margin-right: 14px;
    }
    .note-heading {
        font-size: 8px;
        font-weight: bold;
        color: #0284c7;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        margin-bottom: 3px;
    }
    .note-content {
        font-size: 9px;
        color: #64748b;
        line-height: 1.4;
    }
    
    /* Totals */
    .totals-table {
        width: 100%;
        border-collapse: collapse;
    }
    .totals-table td {
        padding: 2.5px 0;
        font-size: 10px;
        color: #64748b;
    }
    .totals-label {
        text-align: left;
        font-weight: normal;
    }
    .totals-val {
        text-align: right;
        font-weight: bold;
        color: #0f172a;
        white-space: nowrap;
    }
    .grand-total-divider td {
        padding-top: 4px;
        border-top: 1px solid #cbd5e1;
    }
    .grand-total-label {
        font-size: 11.5px;
        font-weight: bold;
        color: #0f172a;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .grand-total-amount {
        font-size: 15px;
        font-weight: bold;
        color: #06b6d4;
        text-align: right;
        white-space: nowrap;
    }
    
    /* Footer */
    .footer {
        margin-top: 18px;
        padding-top: 9px;
        border-top: 1px solid #e2e8f0;
        font-size: 8.5px;
        color: #94a3b8;
        text-align: center;
        line-height: 1.4;
    }
</style>
