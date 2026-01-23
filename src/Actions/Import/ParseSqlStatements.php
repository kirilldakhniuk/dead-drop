<?php

declare(strict_types=1);

namespace KirillDakhniuk\DeadDrop\Actions\Import;

class ParseSqlStatements
{
    public function execute(string $sql): array
    {
        $sql = $this->cleanSql($sql);

        return $this->splitStatements($sql);
    }

    protected function cleanSql(string $sql): string
    {
        // Remove SQL comments
        $sql = preg_replace('/^--.*$/m', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

        // Remove empty lines
        $sql = preg_replace('/^\s*[\r\n]/m', '', $sql);

        return trim($sql);
    }

    protected function splitStatements(string $sql): array
    {
        $statements = [];
        $currentStatement = '';
        $inQuotes = false;
        $quoteChar = null;

        for ($i = 0; $i < strlen($sql); $i++) {
            $char = $sql[$i];

            if (($char === '"' || $char === "'") && ($i === 0 || $sql[$i - 1] !== '\\')) {
                if (! $inQuotes) {
                    $inQuotes = true;
                    $quoteChar = $char;
                } elseif ($char === $quoteChar) {
                    $inQuotes = false;
                    $quoteChar = null;
                }
            }

            if ($char === ';' && ! $inQuotes) {
                $statements[] = trim($currentStatement);
                $currentStatement = '';
            } else {
                $currentStatement .= $char;
            }
        }

        if (trim($currentStatement) !== '') {
            $statements[] = trim($currentStatement);
        }

        return array_filter($statements);
    }
}
