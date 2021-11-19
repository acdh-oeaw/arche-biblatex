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

use acdhOeaw\arche\lib\RepoResourceResolver;
use acdhOeaw\arche\biblatex\Resource;
use zozlak\logging\Log;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');

require_once 'vendor/autoload.php';

$cfg                   = json_decode(json_encode(yaml_parse_file(__DIR__ . '/config.yaml')));
$cfg->biblatex->schema = $cfg->schema;
$log                   = new Log($cfg->biblatex->logFile, $cfg->biblatex->logLevel);
$resolver              = new RepoResourceResolver($cfg, $log);

$id   = filter_input(\INPUT_GET, 'id');
$id   = preg_replace('|/metadata$|', '', $id);
$lang = filter_input(\INPUT_GET, 'lang') ?? $cfg->biblatex->defaultLang;
try {
    $repoRes  = $resolver->resolve($id);
    $res      = new Resource($repoRes, $cfg->biblatex, $log);
    $biblatex = $res->getBiblatex($lang, filter_input(INPUT_GET, 'override'));
    header('Content-Type: application/x-bibtex');
    echo $biblatex;
} catch (Throwable $e) {
    $resolver->handleException($e, $log);
}
