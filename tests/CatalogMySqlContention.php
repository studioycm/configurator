<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** @return array<string, mixed> */
function readCatalogContentionMessage(mixed $channel): array
{
    $line = fgets($channel);
    if ($line === false) {
        throw new RuntimeException('Contention worker did not reach its deterministic barrier before the timeout.');
    }

    return json_decode($line, true, flags: JSON_THROW_ON_ERROR);
}

/** @param array<string, mixed> $message */
function writeCatalogContentionMessage(mixed $channel, array $message): void
{
    if (fwrite($channel, json_encode($message, JSON_THROW_ON_ERROR)."\n") === false) {
        throw new RuntimeException('Could not send the contention barrier message.');
    }
}

/** @return array<string, mixed> */
function catalogContentionResult(Closure $operation): array
{
    try {
        return ['status' => 'committed', 'value' => $operation()];
    } catch (ValidationException $exception) {
        return ['status' => 'rejected', 'errors' => $exception->errors()];
    } catch (Throwable $exception) {
        $databaseException = $exception;
        while (! $databaseException instanceof QueryException && $databaseException->getPrevious() !== null) {
            $databaseException = $databaseException->getPrevious();
        }

        return ['status' => 'exception', 'class' => $exception::class, 'sqlstate' => $databaseException instanceof QueryException ? $databaseException->errorInfo[0] : null, 'driver_code' => $databaseException instanceof QueryException ? $databaseException->errorInfo[1] : null];
    }
}

/**
 * @param  Closure(mixed):array<string, mixed>  $work
 * @return array{pid:int, channel:mixed, connection_id:int}
 */
function startCatalogContentionWorker(Closure $work): array
{
    if (! function_exists('pcntl_fork')) {
        throw new RuntimeException('pcntl_fork is required to execute actual independent-process contention.');
    }
    $channels = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    if ($channels === false) {
        throw new RuntimeException('Local process channels are required for contention tests.');
    }
    $pid = pcntl_fork();
    if ($pid === -1) {
        fclose($channels[0]);
        fclose($channels[1]);
        throw new RuntimeException('Could not fork a contention worker.');
    }
    if ($pid === 0) {
        fclose($channels[0]);
        stream_set_timeout($channels[1], 12);
        DB::purge('mysql');
        assertCatalogMySqlSafety(app());
        DB::statement('SET SESSION innodb_lock_wait_timeout = 5');
        $connectionId = (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
        writeCatalogContentionMessage($channels[1], ['phase' => 'connected', 'connection_id' => $connectionId]);
        readCatalogContentionMessage($channels[1]);
        $result = $work($channels[1]);
        writeCatalogContentionMessage($channels[1], ['phase' => 'result', ...$result]);
        DB::disconnect('mysql');
        fclose($channels[1]);
        exit(0);
    }
    fclose($channels[1]);
    stream_set_timeout($channels[0], 12);
    try {
        $connected = readCatalogContentionMessage($channels[0]);
        if (($connected['phase'] ?? null) !== 'connected' || ! is_int($connected['connection_id'] ?? null)) {
            throw new RuntimeException('Contention worker did not confirm its independent connection.');
        }
    } catch (Throwable $exception) {
        stopCatalogContentionWorkers([['pid' => $pid, 'channel' => $channels[0]]]);
        throw $exception;
    }

    return ['pid' => $pid, 'channel' => $channels[0], 'connection_id' => $connected['connection_id']];
}

/** @param array<array{pid:int, channel:mixed}> $workers */
function stopCatalogContentionWorkers(array $workers): void
{
    foreach ($workers as $worker) {
        if (pcntl_waitpid($worker['pid'], $status, WNOHANG) === 0) {
            posix_kill($worker['pid'], SIGTERM);
            pcntl_waitpid($worker['pid'], $status);
        }
        fclose($worker['channel']);
    }
    DB::purge('mysql');
}
