<?php

declare(strict_types=1);

namespace KirillDakhniuk\DeadDrop\Actions\Export;

use Faker\Factory as FakerFactory;
use Faker\Generator as Faker;

class TransformRowData
{
    protected ?Faker $faker = null;

    public function execute(array $row, array $config): array
    {
        $row = $this->applyDefaults($row, $config['defaults'] ?? []);
        $row = $this->censorFields($row, $config['censor'] ?? []);

        return $row;
    }

    protected function applyDefaults(array $row, array $defaults): array
    {
        foreach ($defaults as $field => $value) {
            if (! array_key_exists($field, $row)) {
                $row[$field] = $this->isPasswordField($field) ? bcrypt($value) : $value;
            }
        }

        return $row;
    }

    protected function censorFields(array $row, array $censorFields): array
    {
        if (empty($censorFields)) {
            return $row;
        }

        foreach ($censorFields as $field => $fakerMethod) {
            if (is_numeric($field)) {
                $field = $fakerMethod;
                $fakerMethod = $this->detectFakerMethod($field);
            }

            if (array_key_exists($field, $row)) {
                $row[$field] = $this->generateFakeValue($fakerMethod);
            }
        }

        return $row;
    }

    protected function isPasswordField(string $fieldName): bool
    {
        $passwordFields = ['password', 'password_hash', 'passwd', 'user_password'];

        return in_array(strtolower($fieldName), $passwordFields);
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

    protected function generateFakeValue(string $method): mixed
    {
        $this->faker ??= FakerFactory::create();

        try {
            return $this->faker->$method;
        } catch (\Exception $e) {
            return '[FAKE_DATA]';
        }
    }
}
