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

use quickRdf\Dataset;
use quickRdf\DataFactory as DF;
use quickRdfIo\Util as RdfIoUtil;
use acdhOeaw\arche\biblatex\Resource as BibResource;

/**
 * Description of ResourceTest
 *
 * @author zozlak
 */
class ResourceTest extends \PHPUnit\Framework\TestCase {

    // primary resource in the tests/meta.ttl
    const RES_URL = 'https://arche.acdh.oeaw.ac.at/api/139852';

    public function testAll(): void {
        $cfg                   = yaml_parse_file(__DIR__ . '/../config-sample.yaml');
        $cfg                   = json_decode(json_encode($cfg));
        $cfg->biblatex->schema = $cfg->schema;

        $graph = new Dataset();
        $graph->add(RdfIoUtil::parse(__DIR__ . '/meta.ttl', new DF(), 'text/turtle'));
        $res   = new RepoResourceStub(self::RES_URL);
        $res->setGraph($graph);

        $biblatex = new BibResource($res, $cfg->biblatex);
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
  keywords = {Bundesländer, Verwaltung, Föderalismus, Zwischenkriegszeit},
  abstract = {Das Protokoll behandelt die 3. Länderkonferenz.}
}
";
        $this->assertEquals($expected, $output);
    }
}
