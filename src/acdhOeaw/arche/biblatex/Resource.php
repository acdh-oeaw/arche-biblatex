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

namespace acdhOeaw\arche\biblatex;

use RuntimeException;
use RenanBr\BibTexParser\Listener as BiblatexL;
use RenanBr\BibTexParser\Parser as BiblatexP;
use RenanBr\BibTexParser\Processor\TagNameCaseProcessor as BiblatexCP;
use RenanBr\BibTexParser\Exception\ParserException as BiblatexE1;
use RenanBr\BibTexParser\Exception\ProcessorException as BiblatexE2;
use zozlak\logging\Log;
use zozlak\RdfConstants as RDF;
use acdhOeaw\arche\lib\RepoResourceInterface;

/**
 * Maps ARCHE resource metadata to a BibLaTeX bibliographic entry.
 * 
 * Fro BibTeX/BibLaTeX bibliographic entry reference see:
 * - https://www.bibtex.com/g/bibtex-format/#fields
 * - chapter 8 of http://tug.ctan.org/info/bibtex/tamethebeast/ttb_en.pdf
 * - https://mirror.kumi.systems/ctan/macros/latex/contrib/biblatex/doc/biblatex.pdf
 * 
 * @author zozlak
 */
class Resource {

    const TYPE_CONST         = 'const';
    const TYPE_PERSON        = 'person';
    const TYPE_CURRENT_DATE  = 'currentDate';
    const TYPE_LITERAL       = 'literal';
    const TYPE_EPRINT        = 'eprint';
    const TYPE_URL           = 'url';
    const SRC_PARENT         = 'parent';
    const SRC_TOP_COLLECTION = 'topCollection';

    private \acdhOeaw\arche\lib\RepoResourceInterface $res;
    private Log $log;
    private object $config;
    private \EasyRdf\Resource $meta;
    private string $lang;
    private object $mapping;

    public function __construct(RepoResourceInterface $res, object $config,
                                Log $log) {
        $this->res    = $res;
        $this->config = $config;
        $this->log    = $log;
    }

    public function getBiblatex(string $lang, ?string $override = null,
                                ?string $property = null): string {
        $this->lang = $lang;
        if (!isset($this->meta)) {
            $this->res->loadMetadata(true, RepoResourceInterface::META_PARENTS);
            $this->meta = $this->res->getGraph();
        }

        $classes = $this->meta->allResources(RDF::RDF_TYPE);
        foreach ($classes as $c) {
            if (isset($this->config->mapping->$c)) {
                $this->mapping = $this->config->mapping->$c;
                break;
            }
        }
        if (!isset($this->mapping)) {
            throw new RuntimeException("Repository resource is of unsupported class", 400);
        }

        $firstLine = "@" . $this->mapping->type . "{" . $this->formatKey();
        $biblatex  = [];
        $mapping   = (array) $this->mapping;
        if (!empty($property)) {
            if (isset($mapping[$property])) {
                $mapping = [$property => $mapping[$property]];
            } else {
                $mapping = [];
            }
        }
        foreach ($mapping as $key => $definition) {
            $key   = mb_strtolower($key);
            $field = $this->formatProperty($definition);
            if (!empty($field)) {
                $field          = $this->escapeBiblatex(trim($field));
                $biblatex[$key] = $field;
            }
        }

        $this->applyOverrides($biblatex);
        if (!empty($override)) {
            $this->applyOverrides($biblatex, $override);
        }

        if (!empty($property)) {
            return $biblatex[$property] ?? '';
        }

        $output = $firstLine;
        foreach ($biblatex as $key => $value) {
            $output .= ",\n  $key = {" . $value . "}";
        }
        $output .= "\n}\n";
        return $output;
    }

    private function applyOverrides(array &$fields, ?string $override = null): void {
        $biblatex = (string) ($override ?? $this->meta->getLiteral($this->config->biblatexProperty));
        if (!empty($biblatex)) {
            if (substr($biblatex, 0, 1) !== '@') {
                $biblatex = "@dataset{foo,\n$biblatex\n}";
            }

            $listener = new BiblatexL();
            $listener->addProcessor(new BiblatexCP(CASE_LOWER));
            $parser   = new BiblatexP();
            $parser->addListener($listener);
            try {
                $parser->parseString($biblatex);
                $entries = $listener->export();
                if (count($entries) !== 1) {
                    throw new RuntimeException("Exactly one BibLaTeX entry expected but " . count($entries) . " parsed: $biblatex");
                }
                foreach ($entries[0] as $key => $value) {
                    if (!in_array($key, ['type', '_type', '_original', 'citation-key'])) {
                        $fields[$key] = $value;
                    }
                }
            } catch (BiblatexE1 $e) {
                $msg = $e->getMessage();
                throw new RuntimeException("Can't parse ($msg): $biblatex");
            } catch (BiblatexE2 $e) {
                $msg = $e->getMessage();
                throw new RuntimeException("Can't parse ($msg): $biblatex");
            }
        }
    }

    /**
     * 
     * @param mixed $definition
     * @return string|null
     * @throws RuntimeException
     */
    private function formatProperty($definition): ?string {
        // simple cases
        if (is_string($definition)) {
            return $this->getLiteral($definition);
        }
        if (is_array($definition)) {
            return $this->formatAll($definition);
        }

        // constant values
        $definition       = (object) $definition;
        $definition->type = $definition->type ?? self::TYPE_LITERAL;
        if ($definition->type === self::TYPE_CONST) {
            return $definition->value;
        } elseif ($definition->type === self::TYPE_CURRENT_DATE) {
            return date('Y-m-d');
        }

        // full resolution
        if (in_array($definition->src ?? null, [self::SRC_PARENT, self::SRC_TOP_COLLECTION])) {
            return $this->formatParent($definition->src, $definition->property);
        }
        switch ($definition->type) {
            case self::TYPE_LITERAL:
                return $this->formatAll($definition->properties);
            case self::TYPE_PERSON:
                return $this->formatPersons($definition->properties);
            case self::TYPE_EPRINT:
                return preg_replace('|^https?://[^/]*/|', '', (string) $this->getLiteral($definition->properties[0]));
            case self::TYPE_URL:
                return $this->formatAll($definition->properties, null, true, $definition->prefNmsp ?? null);
            default:
                throw new RuntimeException('Unsupported property type ' . $definition->type, 500);
        }
    }

    private function formatKey(): string {
        $keyCfg  = $this->config->mapping->key;
        $surname = $this->config->mapping->person->surname;
        $actors  = [];
        foreach ($keyCfg->actors as $property) {
            foreach ($this->meta->allResources($property) as $actor) {
                $actors[] = $this->getLiteral($surname, $actor);
            }
            if (count($actors) > 0) {
                break;
            }
        }
        if (count($actors) > $keyCfg->maxActors) {
            $actors = $actors[0] . '_' . $this->config->etal;
        } else {
            $actors = join('_', $actors);
        }
        $year = substr((string) $this->getLiteral($keyCfg->year), 0, 4);
        $id   = preg_replace('|^.*/|', '', $this->res->getUri());
        return preg_replace('/[^-a-zA-Z0-9_]/', '', "${actors}_${year}_${id}");
    }

    private function formatPerson(\EasyRdf\Resource $person): string {
        $cfg     = $this->config->mapping->person;
        $name    = $this->getLiteral($cfg->name, $person);
        $surname = $this->getLiteral($cfg->surname, $person);
        if (!empty($name) || !empty($surname)) {
            return "$surname, $name";
        } else {
            return '{' . $this->getLiteral($cfg->label, $person) . '}';
        }
    }

    private function getLiteral(string $property,
                                \EasyRdf\Resource $resource = null): ?string {
        $resource = $resource ?? $this->meta;
        $value    = $resource->getLiteral($property, $this->lang) ?? $resource->getLiteral($property);
        return $value !== null ? (string) $value : null;
    }

    /**
     * Gathers all values of given properties.
     * 
     * If a given property has at least one literal value in the preferred language,
     * literal values in other language are discarded.
     * 
     * Empty values are discarded.
     * 
     * @param string[] $properties
     * @param \EasyRdf\Resource $resource
     * @return string|null
     */
    private function formatAll(array $properties,
                               \EasyRdf\Resource $resource = null,
                               bool $onlyUrl = false, ?string $nmsp = null): ?string {
        $resource  = $resource ?? $this->meta;
        $literals  = [];
        $resources = [];
        $values    = [];
        foreach ($properties as $property) {
            foreach ($resource->all($property) as $i) {
                if ($i instanceof \EasyRdf\Resource) {
                    if ($onlyUrl) {
                        $value = $i->getUri();
                    } else {
                        $value = $this->getLiteral($this->config->schema->label, $i);
                    }
                    if (!empty($value)) {
                        $resources[] = strpos($value, ',') !== false ? '{' . $value . '}' : $value;
                    }
                } else {
                    $value = (string) $i;
                    if (!empty($value)) {
                        $lang = (string) $i->getLang();
                        if (!isset($literals[$lang])) {
                            $literals[$lang] = [];
                        }
                        $values[$lang][] = strpos($value, ',') !== false ? '{' . $value . '}' : $value;
                    }
                }
            }
        }
        if (isset($values[$this->lang])) {
            $values = $values[$this->lang];
        } else {
            $values = array_merge(...array_values($values));
        }
        $values = array_unique(array_merge($resources, $values));
        if ($nmsp !== null) {
            $n = strlen($nmsp);
            foreach ($values as $i) {
                if (substr($i, 0, $n) === $nmsp) {
                    return $i;
                }
            }
            return count($values) > 0 ? $values[0] : '';
        }
        return join(', ', $values);
    }

    /**
     * 
     * @param string[] $properties
     * @param \EasyRdf\Resource $resource
     * @return string|null
     */
    private function formatPersons(array $properties,
                                   \EasyRdf\Resource $resource = null): ?string {
        $resource = $resource ?? $this->meta;
        $persons  = [];
        foreach ($properties as $property) {
            foreach ($resource->allResources($property) as $person) {
                $pid = $person->getUri();
                if (!isset($persons[$pid])) {
                    $persons[$pid] = $this->formatPerson($person);
                }
            }
        }
        return join(' and ', $persons);
    }

    private function formatParent(string $type, string $property): ?string {
        $continue = true;
        $res      = $this->meta;
        while ($continue && ($parent   = $res->getResource($this->config->schema->parent))) {
            $res      = $parent;
            $continue &= $type === self::SRC_TOP_COLLECTION;
        }
        if ($res === $this->meta) {
            return null;
        }
        $biblatexRes       = clone $this;
        $biblatexRes->meta = $res;
        $biblatex          = $biblatexRes->getBiblatex($this->lang, null, $property);
        return empty($biblatex) ? null : $biblatex;
    }

    private function escapeBiblatex(string $value): string {
        return $value; // it seems that most important clients, like citation.js, anyway don't support any form of escaping
    }
}
