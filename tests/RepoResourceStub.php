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
use quickRdf\DatasetNode;
use quickRdf\DataFactory as DF;
use rdfInterface\TermInterface;
use rdfInterface\DatasetInterface;
use rdfInterface\DatasetNodeInterface;

/**
 * Description of RepoResourceStub
 *
 * @author zozlak
 */
class RepoResourceStub implements RepoResourceInterface {

    public DatasetNodeInterface $meta;
    public RepoInterface | null $repo;

    public function __construct(string $url, ?RepoInterface $repo = null) {
        $this->meta = new DatasetNode(DF::namedNode($url));
        $this->repo = $repo;
    }

    public function getClasses(): array {
        throw new \RuntimeException();
    }

    public function getGraph(): DatasetNodeInterface {
        return $this->meta;
    }

    public function getIds(): array {
        throw new \RuntimeException();
    }

    public function getMetadata(): DatasetNodeInterface {
        return $this->meta;
    }

    public function getRepo(): RepoInterface {
        throw new \RuntimeException();
    }

    public function getUri(): TermInterface {
        return $this->meta->getNode();
    }

    public function isA(string $class): bool {
        throw new \RuntimeException();
    }

    /**
     * 
     * @param array<string> $resourceProperties
     * @param array<string> $relativesProperties
     */
    public function loadMetadata(bool $force = false,
                                 string $mode = self::META_RESOURCE,
                                 ?string $parentProperty = null,
                                 array $resourceProperties = [],
                                 array $relativesProperties = []): void {
    }

    public function setGraph(DatasetInterface $resource): void {
        $this->meta = $this->meta->withDataset($resource);
    }

    public function setMetadata(DatasetNodeInterface $metadata): void {
        $this->meta = $metadata;
    }
}
