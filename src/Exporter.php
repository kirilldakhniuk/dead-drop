<?php

declare(strict_types=1);

namespace KirillDakhniuk\DeadDrop;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * @internal
 */
final class Exporter
{
    public function exportTable(
        string $table,
        string $connection,
        string $outputPath
    ): array {
        $config = config("dead-drop.tables.{$table}");

        if (! $config || $config === false) {
            throw new \InvalidArgumentException("Table {$table} is not configured for export");
        }

        // Ensure output directory exists
        File::ensureDirectoryExists($outputPath);

        // Build query
        $query = DB::connection($connection)->table($table);

        // Apply column selection
        if (isset($config['columns']) && $config['columns'] !== '*') {
            $query->select($config['columns']);
        }

        // Apply where conditions
        if (isset($config['where'])) {
            foreach ($config['where'] as $condition) {
                $query->where(...$condition);
            }
        }

        // Apply order
        if (isset($config['order_by'])) {
            [$column, $direction] = array_pad(explode(' ', $config['order_by']), 2, 'ASC');
            $query->orderBy($column, trim($direction));
        }

        // Apply limit
        if (isset($config['limit'])) {
            $query->limit($config['limit']);
        }

        // Get data
        $data = $query->get();

        // Apply default values for required fields not in columns
        if (isset($config['defaults']) && is_array($config['defaults'])) {
            $data = $this->applyDefaults($data, $config['defaults']);
        }

        // Apply censoring to sensitive fields
        if (isset($config['censor']) && is_array($config['censor'])) {
            $data = $this->censorFields($data, $config['censor']);
        }

        // Export to SQL
        $filename = $this->exportToSql($table, $data, $outputPath);

        $result = [
            'table' => $table,
            'records' => $data->count(),
            'file' => $filename,
            'size' => File::size($filename),
            'cloud_path' => null,
            'storage_disk' => null,
        ];

        // Upload to cloud storage if configured
        $storageDisk = config('dead-drop.storage.disk');
        if ($storageDisk && $storageDisk !== 'local') {
            $cloudPath = $this->uploadToCloudStorage($filename, $table, $storageDisk);
            $result['cloud_path'] = $cloudPath;
            $result['storage_disk'] = $storageDisk;

            // Delete local file if configured
            if (config('dead-drop.storage.delete_local_after_upload', false)) {
                File::delete($filename);
                $result['local_deleted'] = true;
            }
        }

        return $result;
    }

    public function exportAll(string $outputPath, ?string $connection = null): array
    {
        $connection = $connection ?? config('database.default');
        $tables = config('dead-drop.tables', []);

        $exportedTables = [];

        foreach ($tables as $table => $config) {
            if ($config === false) {
                continue;
            }

            $exportedTables[] = $this->exportTable($table, $connection, $outputPath);
        }

        return $exportedTables;
    }

    protected function exportToSql(string $table, $data, string $outputPath): string
    {
        $filename = "{$outputPath}/{$table}.sql";

        $sql = "-- Data export for table: {$table}\n";
        $sql .= '-- Generated at: '.now()->toDateTimeString()."\n";
        $sql .= "-- Using upsert syntax for safe re-import\n\n";

        $driver = config('database.default');
        $driverType = config("database.connections.{$driver}.driver");

        foreach ($data as $row) {
            $rowArray = (array) $row;
            $columns = implode(', ', array_map(fn ($col) => "`{$col}`", array_keys($rowArray)));
            $values = implode(', ', array_map(function ($value) {
                if (is_null($value)) {
                    return 'NULL';
                }
                if (is_numeric($value)) {
                    return $value;
                }

                return "'".addslashes($value)."'";
            }, array_values($rowArray)));

            // Generate upsert statement based on database driver
            $statement = $this->generateUpsertStatement($table, $rowArray, $columns, $values, $driverType);
            $sql .= $statement."\n";
        }

        File::put($filename, $sql);

        return $filename;
    }

    protected function generateUpsertStatement(string $table, array $row, string $columns, string $values, string $driver): string
    {
        switch ($driver) {
            case 'sqlite':
                // SQLite: INSERT OR REPLACE
                return "INSERT OR REPLACE INTO `{$table}` ({$columns}) VALUES ({$values});";

            case 'mysql':
            case 'mariadb':
                // MySQL: INSERT ... ON DUPLICATE KEY UPDATE
                $updates = [];
                foreach (array_keys($row) as $col) {
                    if ($col !== 'id') { // Don't update the primary key
                        $updates[] = "`{$col}` = VALUES(`{$col}`)";
                    }
                }
                $updateClause = ! empty($updates) ? ' ON DUPLICATE KEY UPDATE '.implode(', ', $updates) : '';

                return "INSERT INTO `{$table}` ({$columns}) VALUES ({$values}){$updateClause};";

            case 'pgsql':
                // PostgreSQL: INSERT ... ON CONFLICT DO UPDATE
                $updates = [];
                foreach (array_keys($row) as $col) {
                    if ($col !== 'id') {
                        $updates[] = "`{$col}` = EXCLUDED.`{$col}`";
                    }
                }
                $updateClause = ! empty($updates) ? ' ON CONFLICT (id) DO UPDATE SET '.implode(', ', $updates) : ' ON CONFLICT DO NOTHING';

                return "INSERT INTO `{$table}` ({$columns}) VALUES ({$values}){$updateClause};";

            default:
                // Fallback to standard INSERT
                return "INSERT INTO `{$table}` ({$columns}) VALUES ({$values});";
        }
    }

    protected function uploadToCloudStorage(string $localFilePath, string $table, string $disk): string
    {
        $storagePath = config('dead-drop.storage.path', 'dead-drop');
        $filename = basename($localFilePath);
        $cloudPath = trim($storagePath, '/').'/'.$filename;

        $storage = Storage::disk($disk);
        $storage->put($cloudPath, File::get($localFilePath));

        return $cloudPath;
    }

    protected function applyDefaults($data, array $defaults)
    {
        return $data->map(function ($row) use ($defaults) {
            $row = (array) $row;

            foreach ($defaults as $field => $value) {
                // Only add default if field is not already present
                if (! array_key_exists($field, $row)) {
                    // Auto-hash password fields for security
                    if ($this->isPasswordField($field)) {
                        $row[$field] = bcrypt($value);
                    } else {
                        $row[$field] = $value;
                    }
                }
            }

            return (object) $row;
        });
    }

    protected function isPasswordField(string $fieldName): bool
    {
        $passwordFields = ['password', 'password_hash', 'passwd', 'user_password'];

        return in_array(strtolower($fieldName), $passwordFields);
    }

    protected function censorFields($data, array $censorFields)
    {
        $faker = \Faker\Factory::create();

        return $data->map(function ($row) use ($censorFields, $faker) {
            $row = (array) $row;

            foreach ($censorFields as $field => $fakerMethod) {
                // Support both simple array ['email', 'name'] and associative ['email' => 'safeEmail']
                if (is_numeric($field)) {
                    $field = $fakerMethod;
                    $fakerMethod = $this->detectFakerMethod($field);
                }

                if (array_key_exists($field, $row)) {
                    $row[$field] = $this->generateFakeValue($faker, $fakerMethod);
                }
            }

            return (object) $row;
        });
    }

    protected function detectFakerMethod(string $fieldName): string
    {
        $fieldName = strtolower($fieldName);

        $mapping = [
            'email' => 'safeEmail',
            'email_address' => 'safeEmail',
            'name' => 'name',
            'first_name' => 'firstName',
            'last_name' => 'lastName',
            'username' => 'userName',
            'phone' => 'phoneNumber',
            'phone_number' => 'phoneNumber',
            'mobile' => 'phoneNumber',
            'address' => 'address',
            'street' => 'streetAddress',
            'street_address' => 'streetAddress',
            'city' => 'city',
            'state' => 'state',
            'zip' => 'postcode',
            'zipcode' => 'postcode',
            'postal_code' => 'postcode',
            'country' => 'country',
            'company' => 'company',
            'job_title' => 'jobTitle',
            'description' => 'sentence',
            'bio' => 'paragraph',
            'website' => 'url',
            'url' => 'url',
            'ip' => 'ipv4',
            'ip_address' => 'ipv4',
            'ipv4' => 'ipv4',
            'ipv6' => 'ipv6',
            'mac_address' => 'macAddress',
            'uuid' => 'uuid',
            'ssn' => 'ssn',
            'credit_card' => 'creditCardNumber',
            'iban' => 'iban',
        ];

        return $mapping[$fieldName] ?? 'word';
    }

    protected function generateFakeValue($faker, string $method)
    {
        try {
            return $faker->$method;
        } catch (\Exception $e) {
            return '[FAKE_DATA]';
        }
    }
}
