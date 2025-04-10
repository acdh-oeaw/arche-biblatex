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

use acdhOeaw\arche\lib\dissCache\Service;
use acdhOeaw\arche\biblatex\Resource;
use zozlak\HttpAccept;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');

require_once 'vendor/autoload.php';

$service = new Service(__DIR__ . '/config.yaml');
$config  = $service->getConfig();
$clbck   = fn($res, $param) => Resource::cacheHandler($res, $param, $config->biblatex, $service->getLog());
$service->setCallback($clbck);

// response format negotation
$format = Resource::MIME_BIBLATEX;
try {
    if (isset($_GET['format'])) {
        $_SERVER['HTTP_ACCEPT'] = $_GET['format'];
    }
    $templates = glob(__DIR__ . '/vendor/citation-style-language/styles/*glob');
    if (!empty($config->biblatex->cslTemplatesDir)) {
        $templates = array_merge($templates, glob($config->biblatex->cslTemplatesDir . '/*csl'));
    }
    $formats = array_merge(
        [Resource::MIME_BIBLATEX, Resource::MIME_CSL_JSON, Resource::MIME_JSON],
        array_map(fn($x) => substr($x, 0, -4), $templates)
    );
    $format  = HttpAccept::getBestMatch($formats);
    $format  = $format->getFullType();
} catch (RuntimeException) {
    
}

$param    = [
    $_GET['lang'] ?? $config->biblatex->defaultLang,
    $_GET['override'] ?? null,
    $format,
];
$response = $service->serveRequest($_GET['id'] ?? '', $param);
$response->send();
