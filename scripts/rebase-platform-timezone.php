<?php

// One-time, backed-up conversion of Laravel's legacy local business dates.
// Run while the application is in maintenance mode; never through the web.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$options = getopt('', ['source:', 'backup:', 'apply']);
$source = new DateTimeZone($options['source'] ?? 'Asia/Dhaka');
$target = new DateTimeZone('Africa/Cairo');
$apply = array_key_exists('apply', $options);

if (DB::table('business_settings')->where('type', 'platform_timezone_rebase')->exists()) {
    throw new RuntimeException('Timezone conversion was already applied; refusing to shift dates twice.');
}
if ($apply && ! $app->isDownForMaintenance()) {
    throw new RuntimeException('Maintenance mode is required before converting existing dates.');
}
if (DB::getDriverName() !== 'mysql') {
    throw new RuntimeException('This audited conversion is for the production MySQL database.');
}

// These infrastructure timestamps belong to tokens, queues, caches or the
// database server, rather than Laravel business records. Epoch and DATE-only
// columns are deliberately not changed either.
$excluded = ['migrations', 'jobs', 'failed_jobs', 'job_batches', 'cache', 'cache_locks', 'sessions', 'personal_access_tokens'];
$schema = DB::select("SELECT c.TABLE_NAME AS t, c.COLUMN_NAME AS c, c.DATA_TYPE AS d, c.COLUMN_KEY AS k, t.ENGINE AS engine FROM INFORMATION_SCHEMA.COLUMNS c JOIN INFORMATION_SCHEMA.TABLES t ON t.TABLE_SCHEMA=c.TABLE_SCHEMA AND t.TABLE_NAME=c.TABLE_NAME WHERE c.TABLE_SCHEMA=DATABASE() ORDER BY c.TABLE_NAME,c.ORDINAL_POSITION");
$tables = [];
foreach ($schema as $column) {
    if (in_array($column->t, $excluded, true) || str_starts_with($column->t, 'oauth_')) continue;
    $tables[$column->t]['engine'] = $column->engine;
    if ($column->k === 'PRI') $tables[$column->t]['keys'][] = $column->c;
    if (in_array($column->d, ['timestamp', 'datetime'], true)) $tables[$column->t]['dates'][] = $column->c;
}

$snapshot = ['source' => $source->getName(), 'target' => $target->getName(), 'captured_at' => gmdate('c'), 'tables' => []];
$plans = [];
DB::beginTransaction();
try {
    foreach ($tables as $table => $definition) {
        $dates = $definition['dates'] ?? [];
        if ($dates === []) continue;
        $keys = $definition['keys'] ?? [];
        $query = DB::table($table)->select(array_unique(array_merge($keys, $dates)));
        $rows = ($apply ? $query->lockForUpdate() : $query)->get();
        if ($rows->isEmpty()) continue;
        if ($definition['engine'] !== 'InnoDB') throw new RuntimeException("Non-transactional table: $table");
        $snapshot['tables'][$table] = ['keys' => $keys, 'dates' => $dates, 'rows' => $rows->map(fn ($row) => (array) $row)->all()];
        foreach ($rows as $row) {
            $original = (array) $row;
            $updates = [];
            foreach ($dates as $column) {
                $value = $original[$column];
                if ($value === null || str_starts_with($value, '0000-')) continue;
                $date = new DateTimeImmutable($value, $source);
                $converted = $date->setTimezone($target);
                $formatted = $converted->format(str_contains($value, '.') ? 'Y-m-d H:i:s.u' : 'Y-m-d H:i:s');
                if ($formatted !== $value) $updates[$column] = $formatted;
            }
            if ($updates !== []) $plans[] = ['table' => $table, 'where' => array_intersect_key($original, array_flip($keys ?: $dates)), 'updates' => $updates];
        }
        echo "$table: ".count($rows)." rows, ".count($dates)." date columns\n";
    }

    echo 'Planned row conversions: '.count($plans)."\n";
    if (! $apply) {
        DB::rollBack();
        echo "DRY_RUN_ONLY; no dates or settings changed\n";
        exit;
    }

    $backup = $options['backup'] ?? '';
    $directory = realpath(dirname($backup));
    if ($backup === '' || $directory === false || str_starts_with($directory.'/', realpath(base_path()).'/') || file_exists($backup)) {
        throw new RuntimeException('Provide a new backup path in an existing private directory outside the site.');
    }
    $archive = gzencode(json_encode($snapshot, JSON_THROW_ON_ERROR), 9);
    if (file_put_contents($backup, $archive, LOCK_EX) !== strlen($archive)) throw new RuntimeException('Backup could not be written.');
    chmod($backup, 0600);
    if (gzdecode(file_get_contents($backup)) !== json_encode($snapshot, JSON_THROW_ON_ERROR)) throw new RuntimeException('Backup verification failed.');

    foreach ($plans as $plan) DB::table($plan['table'])->where($plan['where'])->update($plan['updates']);
    $now = (new DateTimeImmutable('now', $target))->format('Y-m-d H:i:s');
    DB::table('business_settings')->updateOrInsert(['type' => 'timezone'], ['value' => 'Africa/Cairo', 'updated_at' => $now]);
    DB::table('business_settings')->insert(['type' => 'platform_timezone_rebase', 'value' => json_encode(['source' => $source->getName(), 'target' => $target->getName(), 'backup' => $backup, 'rows' => count($plans)]), 'created_at' => $now, 'updated_at' => $now]);
    DB::commit();
    echo "TIMEZONE_REBASE_APPLIED; verified backup=$backup\n";
} catch (Throwable $exception) {
    if (DB::transactionLevel() > 0) DB::rollBack();
    throw $exception;
}
