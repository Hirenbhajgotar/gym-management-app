# Request Demo Feature Documentation

## Overview

The Request Demo feature allows potential customers to submit demo requests through an elegant, professional form on the website. When a request is submitted, the system automatically notifies administrators via both email and WhatsApp.

## Features

✅ **Professional Design**
- Modern gradient background with animated blobs
- Responsive layout for all devices
- Clean, intuitive form interface
- Real-time loading states

✅ **Comprehensive Form Fields**
- Required: Name, Email, Phone, Preferred Contact Method
- Optional: Company Name, Job Title, Gym Size, Best Time to Contact, Additional Message
- Character counter for message field (1000 char limit)

✅ **Smart Validation**
- Client-side and server-side validation
- Real-time error messages
- Custom validation rules for each field

✅ **Security & Rate Limiting**
- Rate limiting: 3 requests per hour per IP address
- IP address and user agent tracking
- Secure data handling

✅ **Dual Notification System**
- Email notifications to admin
- WhatsApp notifications to admin
- Detailed request information in both channels

✅ **Admin Features**
- Track contacted/uncontacted requests
- Add admin notes
- Mark requests as contacted
- View request history with timestamps

## File Structure

```
app/
├── Livewire/Frontend/
│   └── RequestDemo.php                 # Main Livewire component
├── Models/
│   └── RequestContact.php              # Model with scopes and helper methods
├── Notifications/
│   └── RequestDemoNotification.php     # Email & WhatsApp notification
└── Services/
    └── TwilioWhatsAppService.php       # WhatsApp integration (existing)

database/
├── migrations/
│   └── 2025_11_04_144811_create_request_contacts_table.php
└── factories/
    └── RequestContactFactory.php       # Factory for testing/seeding

resources/views/livewire/frontend/
└── request-demo.blade.php              # Beautiful frontend view

routes/
└── web.php                             # Route: /request-demo

tests/Feature/
└── RequestDemoTest.php                 # Comprehensive test coverage

config/
└── app.php                            # Contains contact_email & contact_phone
```

## Installation & Setup

### 1. Run Migration

```bash
php artisan migrate
```

This creates the `request_contacts` table with the following schema:
- Personal information (name, email, phone)
- Company details (company_name, job_title, gym_size)
- Contact preferences (preferred_contact_method, best_time_to_contact)
- Message/notes fields
- Tracking data (ip_address, user_agent, timestamps)
- Admin management (is_contacted, contacted_at, admin_notes)

### 2. Configure Environment Variables

Add to your `.env` file:

```env
CONTACT_EMAIL=admin@yourcompany.com
CONTACT_PHONE=+1234567890

# Twilio WhatsApp Configuration (if not already set)
TWILIO_ACCOUNT_SID=your_account_sid
TWILIO_AUTH_TOKEN=your_auth_token
TWILIO_WHATSAPP_NUMBER=+14155238886
```

### 3. Update config/app.php (Already Done)

The configuration already includes:
```php
'contact_email' => env('CONTACT_EMAIL', 'contact@fitnessflow.com'),
'contact_phone' => env('CONTACT_PHONE', '+919265098227'),
```

### 4. Queue Configuration

Since notifications are queued (`ShouldQueue`), ensure your queue worker is running:

```bash
php artisan queue:work
```

Or for development:
```bash
php artisan queue:listen
```

## Usage

### Frontend Access

Users can access the Request Demo page at:
```
https://yourdomain.com/request-demo
```

Or by clicking the "Request Your Demo" button on the homepage.

### How It Works

1. **User Submits Form**
   - Fills out the demo request form
   - Clicks "Request Demo" button
   - Form data is validated

2. **Data Storage**
   - Request is saved to `request_contacts` table
   - IP address and user agent are captured
   - Timestamp is recorded

3. **Notifications Sent**
   - Email sent to `config('app.contact_email')`
   - WhatsApp message sent to `config('app.contact_phone')`
   - Both include full request details

4. **Rate Limiting Applied**
   - Maximum 3 requests per hour per IP
   - Prevents spam and abuse

5. **Form Reset**
   - Success message displayed
   - Form cleared for new submissions

## Model Usage

### Creating Records

```php
use App\Models\RequestContact;

$request = RequestContact::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'phone' => '+1234567890',
    'company_name' => 'FitZone Gym',
    'gym_size' => 500,
    'preferred_contact_method' => 'email',
    'message' => 'Interested in your software',
    'ip_address' => request()->ip(),
    'user_agent' => request()->userAgent(),
]);
```

### Using Scopes

```php
// Get uncontacted requests
$uncontacted = RequestContact::uncontacted()->get();

// Get contacted requests
$contacted = RequestContact::contacted()->get();

// Get recent requests (last 30 days)
$recent = RequestContact::recent()->get();

// Combine scopes
$recentUncontacted = RequestContact::recent()->uncontacted()->get();
```

### Marking as Contacted

```php
$request = RequestContact::find(1);
$request->markAsContacted('Called and scheduled demo for tomorrow');

// Check status
if ($request->is_contacted) {
    echo "Already contacted on: " . $request->contacted_at;
}
```

## Notification Details

### Email Notification

The email includes:
- Subject: "New Demo Request - [Name]"
- Contact details (name, email, phone)
- Company information (if provided)
- Gym size (if provided)
- Preferred contact method
- Best time to contact (if provided)
- Additional message (if provided)
- Request metadata (submission time, IP address)

### WhatsApp Notification

The WhatsApp message includes:
- Formatted contact details
- Company and gym information
- Message content
- Submission timestamp
- Professional emoji formatting (🔔, ⏰)

## Testing

Run the comprehensive test suite:

```bash
# Run all Request Demo tests
php artisan test --filter=RequestDemoTest

# Run specific test
php artisan test --filter=RequestDemoTest::it_can_submit_demo_request_with_all_fields

# Run all tests
php artisan test
```

### Test Coverage

The test suite covers:
- ✅ Page rendering
- ✅ Form submission with required fields only
- ✅ Form submission with all fields
- ✅ All validation rules (required, email format, min/max length, etc.)
- ✅ IP address and user agent storage
- ✅ Form reset after submission
- ✅ Rate limiting enforcement
- ✅ Model helper methods
- ✅ Route accessibility

## Database Queries

### Admin Dashboard Examples

```php
// Get all uncontacted requests ordered by newest first
$pending = RequestContact::uncontacted()
    ->orderBy('created_at', 'desc')
    ->get();

// Get statistics
$stats = [
    'total' => RequestContact::count(),
    'uncontacted' => RequestContact::uncontacted()->count(),
    'contacted' => RequestContact::contacted()->count(),
    'this_month' => RequestContact::whereMonth('created_at', now()->month)->count(),
];

// Get requests by contact preference
$emailPreference = RequestContact::where('preferred_contact_method', 'email')->get();
$whatsappPreference = RequestContact::where('preferred_contact_method', 'whatsapp')->get();
```

## Factory Usage

Create test data:

```php
use App\Models\RequestContact;

// Create a single request
$request = RequestContact::factory()->create();

// Create multiple requests
$requests = RequestContact::factory()->count(10)->create();

// Create uncontacted request
$uncontacted = RequestContact::factory()->uncontacted()->create();

// Create contacted request
$contacted = RequestContact::factory()->contacted()->create();

// Create recent uncontacted request
$recent = RequestContact::factory()->recent()->uncontacted()->create();
```

## Customization

### Modify Form Fields

Edit `resources/views/livewire/frontend/request-demo.blade.php` to:
- Add new fields
- Change field order
- Modify styling
- Update copy/text

### Adjust Validation Rules

Edit `app/Livewire/Frontend/RequestDemo.php`:

```php
protected function rules(): array
{
    return [
        'name' => ['required', 'string', 'min:2', 'max:255'],
        'email' => ['required', 'email', 'max:255'],
        // Add or modify rules here
    ];
}
```

### Change Rate Limiting

In `app/Livewire/Frontend/RequestDemo.php`:

```php
// Change from 3 requests per hour to 5 requests per hour
if (RateLimiter::tooManyAttempts($key, 5)) { // Changed from 3 to 5
    // ...
}

RateLimiter::hit($key, 3600); // 3600 seconds = 1 hour
```

### Customize Notifications

Edit `app/Notifications/RequestDemoNotification.php`:
- Modify email template
- Change WhatsApp message format
- Add additional notification channels

## Troubleshooting

### Notifications Not Sending

1. **Check Queue Worker**
   ```bash
   php artisan queue:work
   ```

2. **Check Email Configuration**
   ```bash
   php artisan tinker
   Mail::raw('Test', function($msg) { $msg->to('test@example.com'); });
   ```

3. **Check Logs**
   ```bash
   tail -f storage/logs/laravel.log
   ```

### WhatsApp Not Working

1. **Verify Twilio Configuration**
   - Check `.env` variables
   - Verify account SID and auth token
   - Ensure WhatsApp number is correct

2. **Check WhatsApp Service**
   ```bash
   php artisan tinker
   $service = new \App\Services\TwilioWhatsAppService('system');
   $result = $service->sendMessage('+1234567890', 'Test message');
   dd($result);
   ```

### Rate Limiting Issues

Clear rate limiter:
```php
use Illuminate\Support\Facades\RateLimiter;

RateLimiter::clear('request-demo:127.0.0.1');
```

### Database Connection Issues

If migration fails:
1. Check database connection in `.env`
2. Verify database exists
3. Ensure correct credentials
4. Run: `php artisan migrate --force`

## Security Considerations

✅ **Rate Limiting**: Prevents spam (3 requests/hour per IP)
✅ **Validation**: All inputs validated server-side
✅ **SQL Injection**: Protected via Eloquent ORM
✅ **XSS Protection**: Blade escaping enabled
✅ **CSRF Protection**: Laravel CSRF tokens on forms
✅ **Privacy**: IP addresses stored for security, not shared
✅ **Data Sanitization**: User agent and IP properly escaped

## Performance Optimization

- **Queued Notifications**: Notifications sent asynchronously
- **Database Indexes**: Indexed on email, phone, is_contacted, created_at
- **Rate Limiting**: Redis/Cache backed rate limiting
- **Pagination Ready**: Use `paginate()` for large datasets

## Future Enhancements

Potential improvements:
- [ ] Add Filament admin panel resource for managing requests
- [ ] Auto-response email to requester confirming receipt
- [ ] Calendar integration for scheduling demos
- [ ] CRM integration (Salesforce, HubSpot, etc.)
- [ ] SMS notifications in addition to WhatsApp
- [ ] File upload capability for requirements documents
- [ ] Multi-language support
- [ ] Analytics dashboard for conversion tracking

## API Endpoints (Future)

If you need an API endpoint:

```php
// routes/api.php
Route::post('/api/demo-requests', [RequestDemoController::class, 'store']);
```

## Support

For issues or questions:
- Check logs: `storage/logs/laravel.log`
- Review test output: `php artisan test --filter=RequestDemoTest`
- Verify configuration: `php artisan config:cache`

## License

This feature is part of the FitnessFlow Gym Management System.

---

**Created**: November 4, 2025
**Last Updated**: November 4, 2025
**Version**: 1.0.0