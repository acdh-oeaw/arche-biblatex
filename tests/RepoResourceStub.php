<?php

/*
 * The MIT License
 *
 * Copyright 2021 zozlak.
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

namespace acdhOeaw\arche\biblatex\tests;

use acdhOeaw\arche\lib\RepoInterface;
use acdhOeaw\arche\lib\RepoResourceInterface;
use EasyRdf\Graph;
use EasyRdf\Resource;

/**
 * Description of RepoResourceStub
 *
 * @author zozlak
 */
class RepoResourceStub implements RepoResourceInterface {

    public string $url;
    public Resource $meta;

    public function __construct(string $url, ?RepoInterface $repo = null) {
        $this->url  = $url;
    }

    public function getClasses(): array {
        
    }

    public function getGraph(): Resource {
        return $this->meta;
    }

    public function getIds(): array {
        
    }

    public function getMetadata(): Resource {
        return $this->meta;
    }

    public function getRepo(): RepoInterface {
        
    }

    public function getUri(): string {
        return $this->url;
    }

    public function isA(string $class): bool {
        
    }

    public function loadMetadata(bool $force = false,
                                 string $mode = self::META_RESOURCE,
                                 string $parentProperty = null): void {
        
    }

    public function setGraph(Resource $resource): void {
        $this->meta = $resource;
        $this->url  = $resource->getUri();
    }

    public function setMetadata(Resource $metadata): void {
        $this->meta = $metadata;
        $this->url  = $metadata->getUri();
    }
}
