# Dead Drop

Export and import database tables with fake data. Share database dumps without exposing real user information.

## Features

- Export tables to SQL with upsert syntax
- Replace sensitive data with Faker-generated values
- Cloud storage support (S3, DigitalOcean Spaces)
- Works with SQLite, MySQL, and PostgreSQL

## Installation

```bash
composer require kirilldakhniuk/dead-drop
```

Publish the config and migrations:

```bash
php artisan vendor:publish --tag=dead-drop-config
php artisan vendor:publish --tag=dead-drop-migrations
php artisan migrate
```

## Basic Usage

```bash
# Export data
php artisan dead-drop:export

# Import data
php artisan dead-drop:import
```

The commands are interactive. To specify a database connection:

```bash
php artisan dead-drop:export --connection=mysql
php artisan dead-drop:import --connection=tenant_db
```

## Date Filtering

Filter exports by date range interactively:

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
> yesterday
```

Custom intervals support natural language dates via Carbon.

## Facade API

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

// Nova/Filament integration
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

Edit `config/dead-drop.php`:

```php
return [
    'output_path' => storage_path('app/dead-drop'),

    'tables' => [
        'users' => [
            'columns' => ['id', 'name', 'email', 'created_at'],
            'censor' => ['email'],
            'defaults' => ['password' => 'password'], // Auto-hashed with bcrypt
            'where' => [['created_at', '>', now()->subDays(30)]],
            'limit' => 1000,
        ],
    ],
];
```

### Options

```php
'tables' => [
    'table_name' => [
        'columns' => ['id', 'name', 'email'], // or '*' for all
        'censor' => ['email', 'phone'],
        'defaults' => ['password' => 'secret'],
        'where' => [
            ['status', '=', 'active'],
            ['created_at', '>', now()->subYear()],
        ],
        'order_by' => 'created_at DESC',
        'limit' => 1000,
    ],

    // Disable a table
    'logs' => false,
],
```

## Data Anonymization

List fields to anonymize:

```php
'users' => [
    'columns' => '*',
    'censor' => ['email', 'phone', 'address'],
],
```

The package auto-detects common fields and applies appropriate Faker methods:

| Field | Faker Method | Example |
|-------|--------------|---------|
| email | safeEmail | john@example.com |
| name | name | Jane Doe |
| phone | phoneNumber | (555) 123-4567 |
| address | address | 123 Main St |
| city | city | New York |
| ip | ipv4 | 192.168.1.1 |

Auto-detected fields include: email, name, first_name, last_name, phone, address, city, state, zip, country, company, job_title, website, url, ip, ssn, credit_card, and more.

### Custom Faker Methods

Specify the Faker method for more control:

```php
'censor' => [
    'email' => 'companyEmail',
    'bio' => 'paragraph',
    'website' => 'domainName',
],
```

See all available methods at [fakerphp.github.io](https://fakerphp.github.io/formatters/).

## Upsert Support

Exports use upsert syntax for safe re-imports:

- **SQLite**: `INSERT OR REPLACE`
- **MySQL**: `INSERT ... ON DUPLICATE KEY UPDATE`
- **PostgreSQL**: `INSERT ... ON CONFLICT DO UPDATE`

## Default Values

For partial column exports, add defaults for required fields:

```php
'users' => [
    'columns' => ['id', 'name', 'email'],
    'defaults' => ['password' => 'password'],
],
```

Password fields are automatically hashed with bcrypt.

## Cloud Storage

Configure cloud uploads in `.env`:

```env
DEAD_DROP_STORAGE_DISK=s3
DEAD_DROP_STORAGE_PATH=database-exports
DEAD_DROP_DELETE_LOCAL=false

AWS_ACCESS_KEY_ID=your-key
AWS_SECRET_ACCESS_KEY=your-secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket
```

Install the AWS SDK:

```bash
composer require league/flysystem-aws-s3-v3 "^3.0"
```

### DigitalOcean Spaces

Add to `config/filesystems.php`:

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

Configure in `.env`:

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

**NOT NULL constraint errors** - Add defaults for required fields you're not exporting.

**Foreign key constraint errors** - Import parent tables first, or disable foreign key checks temporarily.

**Duplicate entry errors** - This shouldn't happen with Dead Drop exports since they use upsert syntax. External SQL files may cause this.

## Requirements

- PHP 8.2+
- Laravel 11.x

## License

MIT

---

Created by [Kirill Dakhniuk](https://github.com/kirilldakhniuk)
