<?php

/*
 * The MIT License
 *
 * Copyright 2021 Austrian Centre for Digital Humanities.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

use zozlak\logging\Log;
use acdhOeaw\arche\lib\RepoDb;
use acdhOeaw\arche\lib\SearchConfig;
use acdhOeaw\arche\lib\RepoResourceInterface;
use acdhOeaw\arche\lib\exception\NotFound;
use acdhOeaw\arche\lib\dissCache\CachePdo;
use acdhOeaw\arche\lib\dissCache\ResponseCache;
use acdhOeaw\arche\lib\dissCache\RepoWrapperGuzzle;
use acdhOeaw\arche\lib\dissCache\RepoWrapperRepoInterface;
use acdhOeaw\arche\biblatex\Resource;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');

require_once 'vendor/autoload.php';

$cfg                   = json_decode(json_encode(yaml_parse_file(__DIR__ . '/config.yaml')));

$logId = sprintf("%08d", rand(0, 99999999));
$tmpl  = "{TIMESTAMP}:$logId:{LEVEL}\t{MESSAGE}";
$log   = new Log($cfg->log->file, $cfg->log->level, $tmpl);
try {
    $t0 = microtime(true);

    $id      = $_GET['id'] ?? 'no identifer provided';
    $log->info("Getting thumbnail for $id");
    $allowed = false;
    foreach ($config->allowedNmsp as $i) {
        if (str_starts_with($id, $i)) {
            $allowed = true;
            break;
        }
    }
    if (!$allowed) {
        throw new ThumbnailException("Requested resource $id not in allowed namespace", 400);
    }

    $cache = new CachePdo($config->db);

    $repos = [];
    foreach ($config->repoDb ?? [] as $i) {
        $repos[] = new RepoWrapperRepoInterface(RepoDb::factory($i), true);
    }
    $repos[] = new RepoWrapperGuzzle(false);

    $searchConfig                         = new SearchConfig();
    $searchConfig->metadataMode           = $cfg->biblatex->metadataMode ?? RepoResourceInterface::META_RESOURCE;
    $searchConfig->metadataParentProperty = $cfg->biblatex->parentProperty ?? '';
    $searchConfig->resourceProperties     = $cfg->biblatex->resourceProperties ?? [];
    $searchConfig->relativesProperties    = $cfg->biblatex->relativesProperties ?? [];

    $clbck = fn($res, $param) => Resource::cacheHandler($res, $param, $config->biblatex, $log);
    $ttl   = $config->cache->ttl;
    $cache = new ResponseCache($cache, $clbck, $ttl->resource, $ttl->response, $repos, $searchConfig, $log);

    $param    = [
        $_GET['lang'] ?? $cfg->biblatex->defaultLang,
        $_GET['override'] ?? null,
    ];
    $response = $cache->getResponse($param, $id);
    $response->send();
    $log->info("Ended in " . round(microtime(true) - $t0, 3) . " s");
} catch (\Throwable $e) {
    $code              = $e->getCode();
    $ordinaryException = $e instanceof NotFound;
    $logMsg            = "$code: " . $e->getMessage() . ($ordinaryException ? '' : "\n" . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString());
    $log->error($logMsg);

    if ($code < 400 || $code >= 500) {
        $code = 500;
    }
    http_response_code($code);
    if ($ordinaryException) {
        echo $e->getMessage() . "\n";
    } else {
        echo "Internal Server Error\n";
    }
}
