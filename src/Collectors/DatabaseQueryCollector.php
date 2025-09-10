<?php

declare(strict_types=1);

namespace Iamfarhad\Prometheus\Collectors;

use Iamfarhad\Prometheus\Contracts\CollectorInterface;
use Iamfarhad\Prometheus\Prometheus;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Prometheus\Counter;
use Prometheus\Histogram;
use Prometheus\Summary;

final class DatabaseQueryCollector implements CollectorInterface
{
    private ?Counter $queryCounter = null;

    private ?Histogram $queryDurationHistogram = null;

    private ?Summary $queryDurationSummary = null;

    public function __construct(private Prometheus $prometheus)
    {
        $this->registerMetrics();
        $this->registerEventListener();
    }

    public function registerMetrics(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->queryCounter = $this->prometheus->getOrRegisterCounter(
            'database_queries_total',
            'Total number of database queries executed',
            ['connection', 'table', 'operation']
        );

        $buckets = config('prometheus.collectors.database.histogram_buckets', [0.001, 0.005, 0.01, 0.05, 0.1, 0.5, 1.0, 5.0]);

        $this->queryDurationHistogram = $this->prometheus->getOrRegisterHistogram(
            'database_query_duration_seconds',
            'Database query execution time in seconds',
            ['connection', 'table', 'operation'],
            $buckets
        );

        // Summary metric for database query time percentiles
        $quantiles = config('prometheus.collectors.database.summary_quantiles', [0.5, 0.95, 0.99]);
        $maxAge = config('prometheus.collectors.database.summary_max_age', 600); // 10 minutes

        $this->queryDurationSummary = $this->prometheus->getOrRegisterSummary(
            'database_query_duration_seconds_summary',
            'Database query duration summary with quantiles',
            ['connection', 'table', 'operation'],
            $maxAge,
            $quantiles
        );
    }

    protected function registerEventListener(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        DB::listen(function (QueryExecuted $query) {
            $this->recordQuery($query);
        });
    }

    protected function recordQuery(QueryExecuted $query): void
    {
        $connection = $query->connection->getName();
        $table = $this->extractTableName($query->sql);
        $operation = $this->extractOperationType($query->sql);
        $duration = $query->time / 1000; // Convert from milliseconds to seconds

        $labels = [$connection, $table, $operation];

        // Record query count
        if ($this->queryCounter) {
            $this->queryCounter->inc($labels);
        }

        // Record query duration
        if ($this->queryDurationHistogram) {
            $this->queryDurationHistogram->observe($duration, $labels);
        }

        // Record query duration in summary for percentiles
        if ($this->queryDurationSummary) {
            $this->queryDurationSummary->observe($duration, $labels);
        }
    }

    protected function extractTableName(string $sql): string
    {
        // Enhanced table name extraction from SQL
        // Supports CRUD operations and DDL operations
        $sql = trim($sql);

        // SELECT queries - look for FROM clause
        if (preg_match('/\bFROM\s+([`\w]+)/i', $sql, $matches)) {
            return $this->cleanTableName($matches[1]);
        }

        // UPDATE queries
        if (preg_match('/\bUPDATE\s+([`\w]+)/i', $sql, $matches)) {
            return $this->cleanTableName($matches[1]);
        }

        // INSERT queries - handle both "INSERT INTO table" and "INSERT table"
        if (preg_match('/\bINSERT\s+(?:INTO\s+)?([`\w]+)/i', $sql, $matches)) {
            return $this->cleanTableName($matches[1]);
        }

        // DELETE queries
        if (preg_match('/\bDELETE\s+FROM\s+([`\w]+)/i', $sql, $matches)) {
            return $this->cleanTableName($matches[1]);
        }

        // CREATE TABLE queries
        if (preg_match('/\bCREATE\s+(?:TEMPORARY\s+)?TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?([`\w]+)/i', $sql, $matches)) {
            return $this->cleanTableName($matches[1]);
        }

        // DROP TABLE queries
        if (preg_match('/\bDROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?([`\w]+)/i', $sql, $matches)) {
            return $this->cleanTableName($matches[1]);
        }

        // ALTER TABLE queries
        if (preg_match('/\bALTER\s+TABLE\s+([`\w]+)/i', $sql, $matches)) {
            return $this->cleanTableName($matches[1]);
        }

        // TRUNCATE TABLE queries
        if (preg_match('/\bTRUNCATE\s+(?:TABLE\s+)?([`\w]+)/i', $sql, $matches)) {
            return $this->cleanTableName($matches[1]);
        }

        // CREATE INDEX queries - extract table name from ON clause
        if (preg_match('/\bCREATE\s+(?:UNIQUE\s+)?INDEX\s+[`\w]+\s+ON\s+([`\w]+)/i', $sql, $matches)) {
            return $this->cleanTableName($matches[1]);
        }

        // DROP INDEX queries - extract table name from ON clause
        if (preg_match('/\bDROP\s+INDEX\s+[`\w]+\s+ON\s+([`\w]+)/i', $sql, $matches)) {
            return $this->cleanTableName($matches[1]);
        }

        // Default fallback
        return 'unknown';
    }

    protected function extractOperationType(string $sql): string
    {
        $sql = trim(strtoupper($sql));

        if (str_starts_with($sql, 'SELECT')) {
            return 'select';
        }

        if (str_starts_with($sql, 'INSERT')) {
            return 'insert';
        }

        if (str_starts_with($sql, 'UPDATE')) {
            return 'update';
        }

        if (str_starts_with($sql, 'DELETE')) {
            return 'delete';
        }

        if (str_starts_with($sql, 'CREATE')) {
            return 'create';
        }

        if (str_starts_with($sql, 'DROP')) {
            return 'drop';
        }

        if (str_starts_with($sql, 'ALTER')) {
            return 'alter';
        }

        if (str_starts_with($sql, 'TRUNCATE')) {
            return 'truncate';
        }

        // Default fallback
        return 'other';
    }

    protected function cleanTableName(string $tableName): string
    {
        // Remove backticks and clean up the table name
        return trim($tableName, '`');
    }

    public function isEnabled(): bool
    {
        return config('prometheus.enabled', true) &&
            config('prometheus.collectors.database.enabled', true);
    }
}
