# Request Demo Feature - Deployment Checklist

## 🎯 Pre-Deployment Checklist

### Database Setup
- [ ] Run migration: `php artisan migrate`
- [ ] Verify `request_contacts` table exists
- [ ] Check database indexes are created
- [ ] (Optional) Seed test data: `RequestContact::factory()->count(5)->create()`

### Environment Configuration
- [ ] Set `CONTACT_EMAIL` in `.env` file
- [ ] Set `CONTACT_PHONE` in `.env` file (format: +1234567890)
- [ ] Verify `APP_NAME` is set correctly
- [ ] Confirm `APP_URL` is correct
- [ ] Check Twilio credentials are configured (for WhatsApp):
  - [ ] `TWILIO_ACCOUNT_SID`
  - [ ] `TWILIO_AUTH_TOKEN`
  - [ ] `TWILIO_WHATSAPP_NUMBER`

### Mail Configuration
- [ ] Verify mail driver is configured (`MAIL_MAILER`)
- [ ] Test email sending works
- [ ] Confirm `MAIL_FROM_ADDRESS` is set
- [ ] Confirm `MAIL_FROM_NAME` is set

### Queue Configuration
- [ ] Queue driver is configured (database/redis)
- [ ] Queue worker is running: `php artisan queue:work`
- [ ] Setup supervisor/systemd for production queue worker
- [ ] Verify failed jobs table exists: `php artisan queue:failed-table`

### Testing
- [ ] Run test suite: `php artisan test --filter=RequestDemoTest`
- [ ] All 14 tests pass ✅
- [ ] Manually test form submission
- [ ] Verify email notification received
- [ ] Verify WhatsApp notification received
- [ ] Test rate limiting (submit 4 requests quickly)
- [ ] Test validation errors display correctly
- [ ] Test form reset after successful submission

### Security
- [ ] Rate limiting is working (3 requests/hour)
- [ ] CSRF protection is enabled
- [ ] SQL injection protection verified (Eloquent ORM)
- [ ] XSS protection verified (Blade escaping)
- [ ] IP address logging is working

### Frontend
- [ ] `/request-demo` route is accessible
- [ ] Page displays correctly on desktop
- [ ] Page displays correctly on mobile
- [ ] Page displays correctly on tablet
- [ ] All form fields render properly
- [ ] Loading states work correctly
- [ ] Success notification displays
- [ ] Error notifications display
- [ ] Character counter works (message field)
- [ ] Dropdown menus work (contact method, time)

### Homepage Integration
- [ ] "Request Your Demo" button exists on homepage
- [ ] Button redirects to `/request-demo` page
- [ ] Button styling matches design
- [ ] Button is clickable and functional

### Performance
- [ ] Page loads in < 2 seconds
- [ ] Form submission is fast (< 1 second)
- [ ] No console errors in browser
- [ ] No PHP errors in logs
- [ ] Background animations run smoothly

### Documentation
- [ ] `REQUEST_DEMO_FEATURE.md` reviewed
- [ ] `REQUEST_DEMO_SETUP.md` reviewed
- [ ] Team trained on managing requests
- [ ] Admin knows how to mark requests as contacted

## 🚀 Post-Deployment Checklist

### Monitoring (First 24 Hours)
- [ ] Monitor `storage/logs/laravel.log` for errors
- [ ] Check email delivery rates
- [ ] Check WhatsApp delivery rates
- [ ] Monitor database for new requests
- [ ] Check queue worker is processing jobs
- [ ] Monitor server resources (CPU, memory)

### Functionality Testing
- [ ] Submit a real test request from production
- [ ] Confirm email arrives at admin email
- [ ] Confirm WhatsApp arrives at admin phone
- [ ] Verify request appears in database
- [ ] Test marking request as contacted
- [ ] Verify admin notes can be added

### Analytics & Tracking
- [ ] Track number of form views (optional)
- [ ] Track number of submissions
- [ ] Track conversion rate
- [ ] Monitor rate limiting triggers
- [ ] Track response times

## 📊 Weekly Maintenance

- [ ] Review uncontacted requests
- [ ] Clean up old contacted requests (optional)
- [ ] Check notification delivery success rate
- [ ] Monitor queue job failures
- [ ] Review rate limiting logs

## 🔧 Quick Commands Reference

```bash
# Run migration
php artisan migrate

# Start queue worker (development)
php artisan queue:listen

# Start queue worker (production - use supervisor)
php artisan queue:work --sleep=3 --tries=3

# Run tests
php artisan test --filter=RequestDemoTest

# Clear rate limiter (if needed)
php artisan tinker
> RateLimiter::clear('request-demo:127.0.0.1');

# Check database
php artisan tinker
> App\Models\RequestContact::count();
> App\Models\RequestContact::uncontacted()->count();

# Create test data
php artisan tinker
> App\Models\RequestContact::factory()->count(10)->create();

# Clear cache
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Check queue status
php artisan queue:work --once
php artisan queue:failed
```

## ⚠️ Common Issues & Solutions

### Issue: Email not sending
**Solution**: 
1. Check queue worker is running
2. Verify mail configuration in `.env`
3. Check logs: `tail -f storage/logs/laravel.log`
4. Test manually: `Mail::raw('Test', fn($m) => $m->to('test@test.com'));`

### Issue: WhatsApp not sending
**Solution**:
1. Verify Twilio credentials
2. Check phone number format (must include country code)
3. Check Twilio account balance
4. Review logs for error messages

### Issue: Rate limiting too strict
**Solution**:
1. Increase limit in `RequestDemo.php`
2. Change from 3 to 5 or higher
3. Clear existing rate limits

### Issue: Form validation not working
**Solution**:
1. Clear browser cache
2. Check Livewire is loaded
3. Verify JavaScript console for errors
4. Check network tab for AJAX errors

### Issue: Database connection failed
**Solution**:
1. Check MySQL is running (XAMPP)
2. Verify `.env` database credentials
3. Run: `php artisan config:cache`

## 📧 Test Email Template

Send this to verify notifications:

**Subject**: Test Demo Request

**To**: [Your configured CONTACT_EMAIL]

**Expected Content**:
- "New Demo Request - [Test Name]"
- Contact details section
- Request details section
- IP address and timestamp

## 📱 Test WhatsApp Template

**To**: [Your configured CONTACT_PHONE]

**Expected Format**:
```
🔔 *New Demo Request*

*Contact Details:*
• Name: [Name]
• Email: [Email]
• Phone: [Phone]

⏰ Submitted: [Date Time]
```

## ✅ Sign-Off

- [ ] **Developer**: Feature implemented and tested locally
- [ ] **QA**: All test cases pass, UI verified
- [ ] **DevOps**: Queue worker configured, monitoring set up
- [ ] **Product Owner**: Feature approved for production
- [ ] **Admin Team**: Trained on managing demo requests

## 📝 Notes Section

**Date Deployed**: _______________

**Deployed By**: _______________

**Production URL**: _______________

**Issues Found**: 
_______________________________________________
_______________________________________________
_______________________________________________

**Additional Configuration**:
_______________________________________________
_______________________________________________
_______________________________________________

---

**Checklist Version**: 1.0.0  
**Last Updated**: November 4, 2025  
**Status**: Ready for Production ✅