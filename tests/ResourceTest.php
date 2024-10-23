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

use quickRdf\DatasetNode;
use quickRdf\DataFactory as DF;
use quickRdfIo\Util as RdfIoUtil;
use acdhOeaw\arche\lib\Schema;
use acdhOeaw\arche\lib\SearchConfig;
use acdhOeaw\arche\lib\RepoInterface;
use acdhOeaw\arche\lib\RepoResourceInterface;
use acdhOeaw\arche\lib\dissCache\CachePdo;
use acdhOeaw\arche\lib\dissCache\ResponseCache;
use acdhOeaw\arche\lib\dissCache\RepoWrapperGuzzle;
use acdhOeaw\arche\lib\dissCache\ResponseCacheItem;
use acdhOeaw\arche\biblatex\Resource as BibResource;

/**
 * Description of ResourceTest
 *
 * @author zozlak
 */
class ResourceTest extends \PHPUnit\Framework\TestCase {

    // primary resource in the tests/meta.ttl
    const RES_URL = 'https://arche.acdh.oeaw.ac.at/api/139852';

    static private object $cfg;

    static public function setUpBeforeClass(): void {
        self::$cfg = json_decode(json_encode(yaml_parse_file(__DIR__ . '/config.yaml')));
    }

    public function testAll(): void {
        $res      = $this->getRepoResourceStub(__DIR__ . '/meta.ttl');
        $biblatex = new BibResource($res, self::$cfg->biblatex);
        $output   = $biblatex->getBiblatex('en');
        $expected = "@incollection{Steiner_2021_139852,
  title = {3. Länderkonferenz},
  date = {2021-07-26T18:52:22.864223},
  eprint = {21.11115/0000-000E-5942-4},
  eprinttype = {hdl},
  url = {https://hdl.handle.net/21.11115/0000-000E-5942-4},
  urldate = {" . date('Y-m-d') . "},
  author = {Steiner, Guenther},
  editoratype = {compiler},
  booktitle = {Die Große Transformation},
  bookauthor = {{} and Steiner, Guenther and {} and {} and {}},
  note = {sha1:ba29f9d179bb963516cf5d4c7ca268b9555a0602},
  keywords = {Bundesländer, Föderalismus, Verwaltung, Zwischenkriegszeit},
  abstract = {Das Protokoll behandelt die 3. Länderkonferenz.}
}
";
        $this->assertEquals($expected, $output);
    }

    public function testLive(): void {
        $cache = $this->getCache();

        $t0        = microtime(true);
        $response1 = $cache->getResponse(['en', null], self::RES_URL);
        $t1        = microtime(true);
        $response2 = $cache->getResponse(['en', null], self::RES_URL);
        $t2        = microtime(true) - $t1;
        $t1        = $t1 - $t0;

        $body          = "@incollection{Steiner_2021_139852,
  title = {3. Länderkonferenz},
  date = {2021-07-26T18:52:22.864223},
  eprint = {21.11115/0000-000E-5942-4},
  eprinttype = {hdl},
  url = {https://hdl.handle.net/21.11115/0000-000E-5942-4},
  urldate = {" . date('Y-m-d') . "},
  author = {Steiner, Guenther},
  editoratype = {compiler},
  booktitle = {Die Große Transformation},
  bookauthor = {Megner, Karl and Steiner, Guenther and Helfert, Veronika and Garstenauer, Theresa and Becker, Peter},
  note = {sha1:ba29f9d179bb963516cf5d4c7ca268b9555a0602},
  keywords = {Bundesländer, Föderalismus, Verwaltung, Zwischenkriegszeit},
  abstract = {Das Protokoll behandelt die 3. Länderkonferenz.}
}
";
        $expected      = new ResponseCacheItem($body, 200, ['Content-Type' => 'application/x-bibtex'], false);
        $this->assertEquals($expected, $response1);
        $expected->hit = true;
        $this->assertEquals($expected, $response2);
        $this->assertGreaterThan($t2, $t1 / 10);
    }

    private function getRepoResourceStub(string $metaPath): RepoResourceInterface {
        $graph = new DatasetNode(DF::namedNode(self::RES_URL));
        $graph->add(RdfIoUtil::parse($metaPath, new DF(), 'text/turtle'));

        $schema = new Schema([
            "id"          => "https://vocabs.acdh.oeaw.ac.at/schema#hasIdentifier",
            "parent"      => "https://vocabs.acdh.oeaw.ac.at/schema#isPartOf",
            "label"       => "https://vocabs.acdh.oeaw.ac.at/schema#hasTitle",
            "searchMatch" => "search://match",
            "searchCount" => "search://count",
        ]);
        $repo   = $this->createStub(RepoInterface::class);
        $repo->method('getSchema')->willReturn($schema);

        $res = $this->createStub(RepoResourceInterface::class);
        $res->method('getUri')->willReturn($graph->getNode());
        $res->method('getGraph')->willReturn($graph);
        $res->method('getMetadata')->willReturn($graph);
        $res->method('getRepo')->willReturn($repo);
        return $res;
    }

    private function getCache(): ResponseCache {
        foreach (glob('/tmp/cachePdo_*') as $i) {
            unlink($i);
        }
        $db                                   = new CachePdo('sqlite::memory:');
        $clbck                                = fn($res, $param) => BibResource::cacheHandler($res, $param, self::$cfg->biblatex);
        $ttl                                  = self::$cfg->cache->ttl;
        $repos                                = [new RepoWrapperGuzzle(false)];
        $searchConfig                         = new SearchConfig();
        $searchConfig->metadataMode           = self::$cfg->biblatex->metadataMode;
        $searchConfig->metadataParentProperty = self::$cfg->biblatex->parentProperty;
        $searchConfig->resourceProperties     = self::$cfg->biblatex->resourceProperties;
        $searchConfig->relativesProperties    = self::$cfg->biblatex->relativesProperties;

        $cache = new ResponseCache($db, $clbck, $ttl->resource, $ttl->response, $repos, $searchConfig);

        return $cache;
    }
}
