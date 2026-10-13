# Shouman LMS Backend

Laravel modular monolith boundary for the LMS. Business authority, authorization, persistence, xAPI/LRS integration, and operational safeguards remain server-owned.

The repository includes the Laravel entry point, core readiness/request middleware, module provider boundaries, Composer manifests, and CI workflows. Secrets, vendor dependencies, caches, and local runtime files are excluded.

## Checks

```bash
composer validate --no-check-publish
vendor/bin/pint --test
vendor/bin/phpstan analyse
php artisan test
```

Cloud deployment is intentionally not configured yet.
