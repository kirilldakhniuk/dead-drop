# Dead Drop

Export and import your database tables with fake data. Perfect for sharing database dumps without leaking real user information.

## What's This?

Dead Drop lets you export database tables to SQL files with built-in privacy protection. Need to share production data with your team? Just mark the sensitive fields and they'll be replaced with realistic fake data using Faker.

**Key Features:**
- Export tables to SQL with upsert syntax
- Replace sensitive data with fake data automatically
- Cloud storage support (S3, Spaces, etc.)
- Works with SQLite, MySQL, and PostgreSQL

## Installation

```bash
composer require kirilldakhniuk/dead-drop
```

Publish the config file:

```bash
php artisan vendor:publish --tag=dead-drop-config
```

## Basic Usage

```bash
# Export data
php artisan dead-drop:export

# Import data
php artisan dead-drop:import
```

The commands are interactive - just answer the prompts.

Want to use a different database connection? Use the `--connection` flag:

```bash
php artisan dead-drop:export --connection=mysql
php artisan dead-drop:import --connection=tenant_db
```

## Interactive Date Filtering

Filter by date range interactively when exporting:

```bash
php artisan dead-drop:export
```

Choose from preset date ranges or enter a custom interval:

```
Export all configured tables? (yes/no) [no]:
> yes

Filter by date range?
  Today
  Yesterday
  Last 7 days
  Last 30 days
> Custom interval

Filter from date (optional):
Examples: 2024-01-01, last month, 30 days ago, yesterday
> last month

Filter to date (optional):
Examples: 2024-01-01, last month, 30 days ago, yesterday
> yesterday
```

Preset options provide quick access to common date ranges (defaults to "Today"). Custom interval supports natural language dates via Carbon. Date filters work for both single table and batch exports, applied alongside config-based where conditions.

## Facade API

Use the `DeadDrop` facade for programmatic exports:

```php
use KirillDakhniuk\DeadDrop\Facades\DeadDrop;

// Simple export
DeadDrop::export('users');

// With date range
DeadDrop::export('users', [
    'where' => [
        ['created_at', '>=', '2024-01-01'],
        ['created_at', '<=', now()->toDateTimeString()]
    ]
]);

// Export all tables with date filter
DeadDrop::exportAll(null, null, [
    'where' => [
        ['created_at', '>=', now()->subMonth()->toDateTimeString()],
    ]
]);

// Perfect for Nova/Filament
public function handle()
{
    $result = DeadDrop::export('users', [
        'where' => [
            ['created_at', '>=', $this->startDate],
        ]
    ]);

    return Action::download($result['file']);
}
```

## Configuration

Edit `config/dead-drop.php` to configure which tables to export:

```php
return [
    'output_path' => storage_path('app/dead-drop'),

    'tables' => [
        'users' => [
            'columns' => ['id', 'name', 'email', 'created_at'],
            'censor' => ['email'], // Replace emails with fake ones
            'defaults' => ['password' => 'password'], // Auto-hashed with bcrypt
            'where' => [['created_at', '>', now()->subDays(30)]],
            'limit' => 1000,
        ],
    ],
];
```

### Configuration Options

```php
'tables' => [
    'table_name' => [
        'columns' => ['id', 'name', 'email'], // or '*' for all
        'censor' => ['email', 'phone'], // Fields to fake
        'defaults' => ['password' => 'secret'], // Required fields not in columns
        'where' => [
            ['status', '=', 'active'],
            ['created_at', '>', now()->subYear()], // Use Laravel date helpers
        ],
        'order_by' => 'created_at DESC',
        'limit' => 1000,
    ],

    // Disable a table
    'logs' => false,
],
```

**Date filtering:** Use Laravel's date helpers like `now()->subDays(30)`, `now()->subYear()`, `today()`, etc. for dynamic date ranges.

## Data Anonymization

This is the main reason this package exists. Just list the fields you want to anonymize:

```php
'users' => [
    'columns' => '*',
    'censor' => ['email', 'phone', 'address'],
],
```

The package auto-detects common fields and uses the right Faker method:

| Field | Faker Method | Example |
|-------|--------------|---------|
| email | safeEmail | john@example.com |
| name | name | Jane Doe |
| phone | phoneNumber | (555) 123-4567 |
| address | address | 123 Main St |
| city | city | New York |
| ip | ipv4 | 192.168.1.1 |

**Auto-detected fields:**
email, name, first_name, last_name, phone, address, city, state, zip, country, company, job_title, website, url, ip, ssn, credit_card, and more.

### Custom Faker Methods

Want more control? Specify the Faker method:

```php
'censor' => [
    'email' => 'companyEmail',
    'bio' => 'paragraph',
    'website' => 'domainName',
],
```

See all available methods at [fakerphp.github.io](https://fakerphp.github.io/formatters/).

## Upsert Support

All exports use upsert syntax, so you can import the same file multiple times without errors. The package automatically uses the right syntax for your database:

- **SQLite**: `INSERT OR REPLACE`
- **MySQL**: `INSERT ... ON DUPLICATE KEY UPDATE`
- **PostgreSQL**: `INSERT ... ON CONFLICT DO UPDATE`

This means no "duplicate entry" errors. Just re-import whenever you want.

## Default Values

If you're exporting partial columns, you might need defaults for required fields:

```php
'users' => [
    'columns' => ['id', 'name', 'email'],
    'defaults' => ['password' => 'password'], // Prevents NOT NULL errors
],
```

Password fields (`password`, `password_hash`, etc.) are automatically hashed with bcrypt.

## Cloud Storage

Want to upload exports to S3 or DigitalOcean Spaces? Configure it in your `.env`:

```env
DEAD_DROP_STORAGE_DISK=s3
DEAD_DROP_STORAGE_PATH=database-exports
DEAD_DROP_DELETE_LOCAL=false

AWS_ACCESS_KEY_ID=your-key
AWS_SECRET_ACCESS_KEY=your-secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket
```

Make sure you have the AWS SDK installed:

```bash
composer require league/flysystem-aws-s3-v3 "^3.0"
```

### DigitalOcean Spaces

Add this to `config/filesystems.php`:

```php
'spaces' => [
    'driver' => 's3',
    'key' => env('DO_SPACES_KEY'),
    'secret' => env('DO_SPACES_SECRET'),
    'region' => env('DO_SPACES_REGION', 'nyc3'),
    'bucket' => env('DO_SPACES_BUCKET'),
    'endpoint' => env('DO_SPACES_ENDPOINT'),
],
```

Then in `.env`:

```env
DEAD_DROP_STORAGE_DISK=spaces
DO_SPACES_KEY=your-key
DO_SPACES_SECRET=your-secret
DO_SPACES_REGION=nyc3
DO_SPACES_BUCKET=your-bucket
DO_SPACES_ENDPOINT=https://nyc3.digitaloceanspaces.com
```

## Programmatic Usage

```php
use KirillDakhniuk\DeadDrop\Exporter;
use KirillDakhniuk\DeadDrop\Importer;

// Export
$exporter = app(Exporter::class);
$result = $exporter->exportTable('users', 'mysql', storage_path('app/exports'));

// Import
$importer = app(Importer::class);
$result = $importer->importFromFile('/path/to/file.sql', 'mysql');
$result = $importer->importFromCloud('backups/users.sql', 's3', 'mysql');
```

## Troubleshooting

**"NOT NULL constraint" errors?**
Add defaults for required fields you're not exporting.

**"Foreign key constraint" errors?**
Import parent tables first, or temporarily disable foreign key checks.

**"Duplicate entry" errors?**
Shouldn't happen - the package uses upsert syntax. If you're importing external SQL files, that's why.

## Requirements

- PHP 8.2+
- Laravel 11.x

## License

MIT

---

Created by [Kirill Dakhniuk](https://github.com/kirilldakhniuk)
