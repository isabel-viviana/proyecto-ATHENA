<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Wompi sandbox payments

Payments use Wompi Colombia's hosted Web Checkout in COP. ATHENA creates a unique reference and integrity signature from the server-side plan price; the browser is redirected to Wompi and never sends card data to this API. A subscription stays pending until a signed `transaction.updated` webhook reports an approved transaction with the expected reference, amount, and currency. Renewals are manual.

Configure these values in the Laravel environment using credentials from the Wompi sandbox dashboard. Do not commit credentials:

```dotenv
WOMPI_ENVIRONMENT=test
WOMPI_PUBLIC_KEY=pub_test_...
WOMPI_INTEGRITY_SECRET=test_integrity_...
WOMPI_EVENTS_SECRET=test_events_...
WOMPI_CHECKOUT_URL=https://checkout.wompi.co/p/
WOMPI_REDIRECT_URL=https://your-frontend.example/payments/return
```

Set the sandbox dashboard's event URL to `https://your-api.example/api/payments/webhook` and enable `transaction.updated`. This integration does not add or alter database tables and does not require running migrations.

Authenticated API endpoints:

- `GET /api/payments/plans` returns the monthly, quarterly, and annual options, including whether each can be purchased. Monthly and annual prices come from the existing `ATHENA Pro` and `ATHENA Anual` rows. The quarterly option stays unavailable unless an `ATHENA Premium trimestral` row with a configured nonzero price and `is_active=true` already exists.
- `POST /api/payments/checkout` accepts `{"plan_key":"monthly"}` (or `quarterly` / `annual`) and returns a `checkout_url` for browser redirection. The server chooses the amount.
- `GET /api/payments/subscriptions/{subscriptionId}` returns the authenticated user's subscription status.

The webhook endpoint is public for Wompi delivery, but validates Wompi's event checksum, sandbox environment, and a signed payment reference before it changes a subscription. Only the webhook—not the browser redirect—can activate a subscription. To honor the no-schema-change constraint, the integration stores only the pending/active subscription state; it does not persist Wompi transaction IDs or add entries to `payment_transactions`, whose existing provider constraint does not include Wompi.
