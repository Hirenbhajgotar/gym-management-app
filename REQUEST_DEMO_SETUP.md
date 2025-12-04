# Request Demo Feature - Quick Setup Guide

## ✅ What Was Created

A complete Request Demo feature with:
- **Professional Form Page** at `/request-demo`
- **Database Schema** for storing demo requests
- **Email & WhatsApp Notifications** to admin
- **Rate Limiting** (3 requests/hour per IP)
- **Comprehensive Tests** with 14 test cases
- **Model with Helper Methods** for easy management

## 🚀 Quick Start

### Step 1: Run Migration

```bash
php artisan migrate
```

This creates the `request_contacts` table.

### Step 2: Configure Environment

Add to `.env`:

```env
CONTACT_EMAIL=your-admin-email@example.com
CONTACT_PHONE=+1234567890

# For WhatsApp (if not already configured)
TWILIO_ACCOUNT_SID=your_twilio_sid
TWILIO_AUTH_TOKEN=your_twilio_token
TWILIO_WHATSAPP_NUMBER=+14155238886
```

### Step 3: Start Queue Worker

```bash
php artisan queue:work
```

Or for development:
```bash
php artisan queue:listen
```

### Step 4: Test It!

Visit: `http://localhost/request-demo`

Or click the "Request Your Demo" button on the homepage.

## 📁 Files Created

```
✅ app/Models/RequestContact.php
✅ app/Livewire/Frontend/RequestDemo.php
✅ app/Notifications/RequestDemoNotification.php
✅ database/migrations/2025_11_04_144811_create_request_contacts_table.php
✅ database/factories/RequestContactFactory.php
✅ resources/views/livewire/frontend/request-demo.blade.php
✅ tests/Feature/RequestDemoTest.php
✅ REQUEST_DEMO_FEATURE.md (comprehensive documentation)
```

## 📝 Files Modified

```
✅ routes/web.php - Added route: Route::get('/request-demo', RequestDemo::class)
✅ app/Livewire/Frontend/HomeController.php - Added requestDemo() method
```

## 🧪 Run Tests

```bash
# Run all Request Demo tests
php artisan test --filter=RequestDemoTest

# All tests should pass ✅
```

## 🎨 Features

### Form Fields
- **Required**: Name, Email, Phone, Preferred Contact Method
- **Optional**: Company Name, Job Title, Gym Size, Best Time to Contact, Message

### Validation
- Email format validation
- Phone minimum 10 characters
- Name minimum 2 characters
- Message maximum 1000 characters
- Gym size must be integer

### Security
- Rate limiting (3 requests per hour per IP)
- CSRF protection
- SQL injection prevention via Eloquent
- XSS protection via Blade escaping

### Notifications
When a demo request is submitted:
1. ✉️ **Email** sent to `config('app.contact_email')`
2. 📱 **WhatsApp** sent to `config('app.contact_phone')`

Both include full request details!

## 🔗 Integration with Homepage

The "Request Your Demo" button on the homepage now links to the new page:

```php
// In HomeController.php
public function requestDemo()
{
    return redirect()->route('request-demo');
}
```

And the button on homepage:
```html
<button wire:click="requestDemo" ...>
    Request Your Demo
</button>
```

## 📊 Database Schema

The `request_contacts` table includes:

| Field | Type | Description |
|-------|------|-------------|
| name | string | Full name (required) |
| email | string | Email address (required) |
| phone | string | Phone number (required) |
| company_name | string | Company/gym name (optional) |
| job_title | string | Job title (optional) |
| gym_size | integer | Number of members (optional) |
| message | text | Additional message (optional) |
| preferred_contact_method | string | email/phone/whatsapp (required) |
| best_time_to_contact | string | Preferred time slot (optional) |
| ip_address | string | IP address (auto-captured) |
| user_agent | string | Browser info (auto-captured) |
| is_contacted | boolean | Contact status (default: false) |
| contacted_at | timestamp | When contacted (nullable) |
| admin_notes | text | Admin notes (nullable) |

## 💡 Usage Examples

### Query Uncontacted Requests

```php
use App\Models\RequestContact;

$pending = RequestContact::uncontacted()->get();
```

### Mark as Contacted

```php
$request = RequestContact::find(1);
$request->markAsContacted('Called and scheduled demo');
```

### Get Recent Requests

```php
$recent = RequestContact::recent()->get(); // Last 30 days
```

## 🛠️ Customization

### Change Rate Limit
Edit `app/Livewire/Frontend/RequestDemo.php`:
```php
if (RateLimiter::tooManyAttempts($key, 5)) { // Change from 3 to 5
```

### Modify Form
Edit `resources/views/livewire/frontend/request-demo.blade.php`

### Update Validation
Edit rules in `app/Livewire/Frontend/RequestDemo.php`

## 🐛 Troubleshooting

### Notifications Not Sending?

1. **Check queue worker is running:**
   ```bash
   php artisan queue:work
   ```

2. **Check logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

3. **Test email manually:**
   ```bash
   php artisan tinker
   Mail::raw('Test', fn($m) => $m->to('test@example.com'));
   ```

### Database Connection Error?

Make sure your database is running:
- XAMPP: Start MySQL in control panel
- Check `.env` database settings

### Rate Limit Issue?

Clear rate limiter:
```php
use Illuminate\Support\Facades\RateLimiter;
RateLimiter::clear('request-demo:127.0.0.1');
```

## 📚 Documentation

See `REQUEST_DEMO_FEATURE.md` for:
- Detailed technical documentation
- API usage examples
- Security considerations
- Performance optimization tips
- Future enhancement ideas

## ✨ Demo Data

Create test data:

```bash
php artisan tinker
```

```php
// Create sample requests
App\Models\RequestContact::factory()->count(10)->create();

// Create uncontacted requests
App\Models\RequestContact::factory()->uncontacted()->count(5)->create();
```

## 🎯 Next Steps

1. ✅ Run migration: `php artisan migrate`
2. ✅ Configure `.env` with contact email and phone
3. ✅ Start queue worker: `php artisan queue:work`
4. ✅ Test the form at `/request-demo`
5. ✅ Check email/WhatsApp notifications arrive
6. 🔮 Optional: Create Filament resource to manage requests in admin panel

## 📞 Support

If you encounter issues:
1. Check `storage/logs/laravel.log`
2. Run tests: `php artisan test --filter=RequestDemoTest`
3. Verify configuration: `php artisan config:cache`

---

**Feature Version**: 1.0.0  
**Created**: November 4, 2025  
**Status**: ✅ Ready for Production