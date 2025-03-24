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

    public function testAllBiblatex(): void {
        $res      = $this->getRepoResourceStub(__DIR__ . '/meta.ttl');
        $biblatex = new BibResource($res, self::$cfg->biblatex);
        $output   = $biblatex->getBiblatex('en');
        $expected = "@incollection{Steiner_2021_139852,
  title = {3. Länderkonferenz},
  urldate = {" . date('Y-m-d') . "},
  date = {2021-07-26},
  publisher = {ARCHE},
  url = {https://hdl.handle.net/21.11115/0000-000E-5942-4},
  author = {Steiner, Guenther},
  language = {DE},
  booktitle = {Die Große Transformation},
  bookauthor = { and  and  and  and Steiner, Guenther},
  note = {sha1:ba29f9d179bb963516cf5d4c7ca268b9555a0602},
  keywords = {Bundesländer, Föderalismus, Verwaltung, Zwischenkriegszeit},
  abstract = {Das Protokoll behandelt die 3. Länderkonferenz.},
  doi = {10.1515/IPRG.2009.011}
}
";
//  eprint = {21.11115/0000-000E-5942-4},
//  eprinttype = {hdl},
        $this->assertEquals($expected, $output);
    }

    public function testAllCsl(): void {
        $res      = $this->getRepoResourceStub(__DIR__ . '/meta.ttl');
        $biblatex = new BibResource($res, self::$cfg->biblatex);
        $output   = $biblatex->getCsl('en');
        $expected = [
            'id'               => 'Steiner_2021_139852',
            'type'             => 'entry',
            'title'            => '3. Länderkonferenz',
            'accessed'         => ['raw' => date('Y-m-d')],
            'available-date'   => ['raw' => '2021-07-26'],
            'publisher'        => 'ARCHE',
            'URL'              => 'https://hdl.handle.net/21.11115/0000-000E-5942-4',
            'author'           => [['family' => 'Steiner', 'given' => 'Guenther']],
            'language'         => 'DE',
            'container-title'  => 'Die Große Transformation',
            'container-author' => [[], [], [], [], ['family' => 'Steiner', 'given' => 'Guenther']],
            'note'             => 'sha1:ba29f9d179bb963516cf5d4c7ca268b9555a0602',
            'keyword'          => 'Bundesländer, Föderalismus, Verwaltung, Zwischenkriegszeit',
            'abstract'         => 'Das Protokoll behandelt die 3. Länderkonferenz.',
            'DOI'              => '10.1515/IPRG.2009.011',
        ];
        $this->assertEquals($expected, $output);
    }

    public function testLiveBiblatex(): void {
        $cache = $this->getCache();

        $t0        = microtime(true);
        $response1 = $cache->getResponse(['en', null, BibResource::MIME_BIBLATEX], self::RES_URL);
        $t1        = microtime(true);
        $response2 = $cache->getResponse(['en', null, BibResource::MIME_BIBLATEX], self::RES_URL);
        $t2        = microtime(true) - $t1;
        $t1        = $t1 - $t0;

        $body          = "@incollection{Steiner_2021_139852,
  title = {3. Länderkonferenz},
  urldate = {" . date('Y-m-d') . "},
  date = {2021-07-26},
  publisher = {ARCHE},
  url = {https://hdl.handle.net/21.11115/0000-000E-5942-4},
  author = {Steiner, Guenther},
  language = {DE},
  booktitle = {Die Große Transformation},
  bookauthor = {Becker, Peter and Garstenauer, Theresa and Helfert, Veronika and Megner, Karl and Steiner, Guenther},
  note = {sha1:ba29f9d179bb963516cf5d4c7ca268b9555a0602},
  keywords = {Bundesländer, Föderalismus, Verwaltung, Zwischenkriegszeit},
  abstract = {Das Protokoll behandelt die 3. Länderkonferenz.}
}
";
//  eprint = {21.11115/0000-000E-5942-4},
//  eprinttype = {hdl},
        $expected      = new ResponseCacheItem($body, 200, ['Content-Type' => BibResource::MIME_BIBLATEX], false);
        $this->assertEquals($expected, $response1);
        $expected->hit = true;
        $this->assertEquals($expected, $response2);
        $this->assertGreaterThan($t2, $t1 / 10);
    }

    public function testLiveCsl(): void {
        $cache = $this->getCache();

        $t0        = microtime(true);
        $response1 = $cache->getResponse(['en', null, BibResource::MIME_CSL_JSON], self::RES_URL);
        $t1        = microtime(true);
        $response2 = $cache->getResponse(['en', null, BibResource::MIME_CSL_JSON], self::RES_URL);
        $t2        = microtime(true) - $t1;
        $t1        = $t1 - $t0;

        $body          = [
            'id'               => 'Steiner_2021_139852',
            'type'             => 'entry',
            'title'            => '3. Länderkonferenz',
            'accessed'         => ['raw' => date('Y-m-d')],
            'available-date'   => ['raw' => '2021-07-26'],
            'publisher'        => 'ARCHE',
            'URL'              => 'https://hdl.handle.net/21.11115/0000-000E-5942-4',
            'author'           => [['family' => 'Steiner', 'given' => 'Guenther']],
            'language'         => 'DE',
            'container-title'  => 'Die Große Transformation',
            'container-author' => [
                ['family' => 'Becker', 'given' => 'Peter'],
                ['family' => 'Garstenauer', 'given' => 'Theresa'],
                ['family' => 'Helfert', 'given' => 'Veronika'],
                ['family' => 'Megner', 'given' => 'Karl'],
                ['family' => 'Steiner', 'given' => 'Guenther'],
            ],
            'note'             => 'sha1:ba29f9d179bb963516cf5d4c7ca268b9555a0602',
            'keyword'          => 'Bundesländer, Föderalismus, Verwaltung, Zwischenkriegszeit',
            'abstract'         => 'Das Protokoll behandelt die 3. Länderkonferenz.',
        ];
        $body          = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $expected      = new ResponseCacheItem($body, 200, ['Content-Type' => BibResource::MIME_CSL_JSON], false);
        $this->assertEquals($expected, $response1);
        $expected->hit = true;
        $this->assertEquals($expected, $response2);
        $this->assertGreaterThan($t2, $t1 / 10);
    }

    public function testOverrideCsl(): void {
        $override = "@inbook{gugl2008,
  author = {Gugl, Christian},
  title = {Mapping and analysis of linear landscape features},
  booktitle = {Geoinformation technologies for geo-cultural landscapes: European Perspectives},
  year = {2008},
  address = {London},
  editor = {Vassilopoulos, Andreas and Evelpidou, Niki and Bender, Oliver and Krek, Alenka},
  doi = {https://doi.org/10.1201/9780203881613}
}";
        $cache    = $this->getCache();

        $t0        = microtime(true);
        $response1 = $cache->getResponse(['en', $override, BibResource::MIME_JSON], self::RES_URL);
        $t1        = microtime(true);
        $response2 = $cache->getResponse(['en', $override, BibResource::MIME_JSON], self::RES_URL);
        $t2        = microtime(true) - $t1;
        $t1        = $t1 - $t0;

        $output        = [
            'id'               => 'gugl2008',
            'type'             => 'chapter',
            'title'            => 'Mapping and analysis of linear landscape features',
            'accessed'         => ['raw' => date('Y-m-d')],
            'available-date'   => ['raw' => '2008'],
            'publisher'        => 'ARCHE',
            'URL'              => 'https://hdl.handle.net/21.11115/0000-000E-5942-4',
            'author'           => [['family' => 'Gugl', 'given' => 'Christian']],
            'language'         => 'DE',
            'container-title'  => 'Geoinformation technologies for geo-cultural landscapes: European Perspectives',
            'container-author' => [
                ['family' => 'Becker', 'given' => 'Peter'],
                ['family' => 'Garstenauer', 'given' => 'Theresa'],
                ['family' => 'Helfert', 'given' => 'Veronika'],
                ['family' => 'Megner', 'given' => 'Karl'],
                ['family' => 'Steiner', 'given' => 'Guenther'],
            ],
            'note'             => 'sha1:ba29f9d179bb963516cf5d4c7ca268b9555a0602',
            'keyword'          => 'Bundesländer, Föderalismus, Verwaltung, Zwischenkriegszeit',
            'abstract'         => 'Das Protokoll behandelt die 3. Länderkonferenz.',
            'publisher-place'  => 'London',
            'editor'           => [
                ['family' => 'Vassilopoulos', 'given' => 'Andreas'],
                ['family' => 'Evelpidou', 'given' => 'Niki'],
                ['family' => 'Bender', 'given' => 'Oliver'],
                ['family' => 'Krek', 'given' => 'Alenka'],
            ],
            'DOI'              => 'https://doi.org/10.1201/9780203881613',
        ];
        $body          = json_encode($output, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $expected      = new ResponseCacheItem($body, 200, ['Content-Type' => BibResource::MIME_CSL_JSON], false);
        $this->assertEquals($expected, $response1);
        $expected->hit = true;
        $this->assertEquals($expected, $response2);
        $this->assertGreaterThan($t2, $t1 / 10);
    }

    public function testOverrideBiblatex(): void {
        $override = "@inbook{gugl2008,
  author = {Gugl, Christian},
  title = {Mapping and analysis of linear landscape features},
  booktitle = {Geoinformation technologies for geo-cultural landscapes: European Perspectives},
  year = {2008},
  address = {London},
  editor = {Vassilopoulos, Andreas and Evelpidou, Niki and Bender, Oliver and Krek, Alenka},
  doi = {https://doi.org/10.1201/9780203881613}
}";
        $cache    = $this->getCache();

        $t0        = microtime(true);
        $response1 = $cache->getResponse(['en', $override], self::RES_URL);
        $t1        = microtime(true);
        $response2 = $cache->getResponse(['en', $override], self::RES_URL);
        $t2        = microtime(true) - $t1;
        $t1        = $t1 - $t0;

        $body          = "@inbook{gugl2008,
  title = {Mapping and analysis of linear landscape features},
  urldate = {" . date('Y-m-d') . "},
  date = {2008},
  publisher = {ARCHE},
  url = {https://hdl.handle.net/21.11115/0000-000E-5942-4},
  author = {Gugl, Christian},
  language = {DE},
  booktitle = {Geoinformation technologies for geo-cultural landscapes: European Perspectives},
  bookauthor = {Becker, Peter and Garstenauer, Theresa and Helfert, Veronika and Megner, Karl and Steiner, Guenther},
  note = {sha1:ba29f9d179bb963516cf5d4c7ca268b9555a0602},
  keywords = {Bundesländer, Föderalismus, Verwaltung, Zwischenkriegszeit},
  abstract = {Das Protokoll behandelt die 3. Länderkonferenz.},
  address = {London},
  editor = {Vassilopoulos, Andreas and Evelpidou, Niki and Bender, Oliver and Krek, Alenka},
  doi = {https://doi.org/10.1201/9780203881613}
}
";
//  eprint = {21.11115/0000-000E-5942-4},
//  eprinttype = {hdl},
        $expected      = new ResponseCacheItem($body, 200, ['Content-Type' => BibResource::MIME_BIBLATEX], false);
        $this->assertEquals($expected, $response1);
        $expected->hit = true;
        $this->assertEquals($expected, $response2);
        $this->assertGreaterThan($t2, $t1 / 10);
    }

    private function getRepoResourceStub(string $metaPath): RepoResourceInterface {
        $graph = new DatasetNode(DF::namedNode(self::RES_URL));
        $graph->add(RdfIoUtil::parse($metaPath, new DF(), 'text/turtle'));

        $res = $this->createStub(RepoResourceInterface::class);
        $res->method('getUri')->willReturn($graph->getNode());
        $res->method('getGraph')->willReturn($graph);
        $res->method('getMetadata')->willReturn($graph);
        return $res;
    }

    private function getCache(): ResponseCache {
        foreach (glob('/tmp/cachePdo_*') as $i) {
            unlink($i);
        }
        $cfg                                  = self::$cfg->dissCacheService;
        $db                                   = new CachePdo('sqlite::memory:');
        $clbck                                = fn($res, $param) => BibResource::cacheHandler($res, $param, self::$cfg->biblatex);
        $repos                                = [new RepoWrapperGuzzle(false)];
        $searchConfig                         = new SearchConfig();
        $searchConfig->metadataMode           = $cfg->metadataMode;
        $searchConfig->metadataParentProperty = $cfg->parentProperty;
        $searchConfig->resourceProperties     = $cfg->resourceProperties;
        $searchConfig->relativesProperties    = $cfg->relativesProperties;

        $cache = new ResponseCache($db, $clbck, $cfg->ttl->resource, $cfg->ttl->response, $repos, $searchConfig);

        return $cache;
    }
}
