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
use Psr\Log\LoggerInterface;
use RenanBr\BibTexParser\Listener as BiblatexL;
use RenanBr\BibTexParser\Parser as BiblatexP;
use RenanBr\BibTexParser\Processor\TagNameCaseProcessor as BiblatexCP;
use RenanBr\BibTexParser\Exception\ParserException as BiblatexE1;
use RenanBr\BibTexParser\Exception\ProcessorException as BiblatexE2;
use rdfInterface\DatasetInterface;
use rdfInterface\TermInterface;
use rdfInterface\LiteralInterface;
use termTemplates\PredicateTemplate as PT;
use termTemplates\QuadTemplate as QT;
use termTemplates\LiteralTemplate as LT;
use acdhOeaw\arche\lib\Schema;
use acdhOeaw\arche\lib\RepoResourceInterface;
use acdhOeaw\arche\lib\dissCache\ResponseCacheItem;
use zozlak\logging\Log;
use zozlak\RdfConstants as RDF;

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

    const NO_OVERRIDE        = 'NOOVERRIDE';
    const TYPE_CONST         = 'const';
    const TYPE_PERSON        = 'person';
    const TYPE_CURRENT_DATE  = 'currentDate';
    const TYPE_LITERAL       = 'literal';
    const TYPE_EPRINT        = 'eprint';
    const TYPE_URL           = 'url';
    const SRC_PARENT         = 'parent';
    const SRC_TOP_COLLECTION = 'topCollection';

    /**
     * @param array<mixed> $param
     */
    static public function cacheHandler(RepoResourceInterface $res,
                                        array $param, object $config,
                                        ?LoggerInterface $log = null): ResponseCacheItem {

        $bibRes   = new self($res, $config, $log);
        $biblatex = $bibRes->getBiblatex(...$param);
        return new ResponseCacheItem($biblatex, 200, ['Content-Type' => 'application/x-bibtex']);
    }

    private RepoResourceInterface $res;
    private Schema $schema;
    private ?LoggerInterface $log = null;
    private object $config;
    private DatasetInterface $meta;
    private TermInterface $node;
    private string $lang;
    private object $mapping;

    public function __construct(RepoResourceInterface $res, object $config,
                                ?LoggerInterface $log = null) {
        $this->res    = $res;
        $this->schema = new Schema($config->schema);
        $this->config = $config;
        $this->log    = $log;
        $this->node   = $this->res->getUri();
        $this->meta   = $this->res->getGraph()->getDataset();
    }

    public function getBiblatex(string $lang, ?string $override = null,
                                ?string $property = null): string {
        $this->lang = $lang;
        
        $classes    = $this->meta->listObjects(new QT($this->node, RDF::RDF_TYPE))->getValues();
        foreach ($classes as $c) {
            if (isset($this->config->mapping->$c)) {
                $this->mapping = $this->config->mapping->$c;
                break;
            }
        }
        if (!isset($this->mapping)) {
            throw new RuntimeException("Repository resource is of unsupported class", 400);
        }

        $biblatex = [];
        $mapping  = (array) $this->mapping;
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

        // overrides from $.cfg.biblatexProperty in metadata
        $this->applyOverrides($biblatex);
        // overrides from $override parameter (e.g. from HTTP request parameter)
        if (!empty($override)) {
            $this->applyOverrides($biblatex, $override);
        }

        if (!empty($property)) {
            return $biblatex[$property] ?? '';
        }

        $output = "@" . $this->mapping->type . "{" . $this->formatKey();
        foreach ($biblatex as $key => $value) {
            if (!empty($value)) {
                $output .= ",\n  $key = {" . $value . "}";
            }
        }
        $output .= "\n}\n";
        return $output;
    }

    /**
     * 
     * @param array<string, string> $fields
     * @param string|null $override
     * @return void
     * @throws RuntimeException
     */
    private function applyOverrides(array &$fields, ?string $override = null): void {
        $biblatex = trim((string) ($override ?? (string) $this->meta->getObject(new QT($this->node, $this->config->biblatexProperty))));
        if (!empty($biblatex)) {
            $this->log?->debug("Applying overrides from " . ($override === null ? 'metadata' : 'parameter'));
            if (substr($biblatex, 0, 1) !== '@') {
                $biblatex = "@" . self::NO_OVERRIDE . "{" . self::NO_OVERRIDE . ",\n$biblatex\n}";
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
                    if (!in_array($key, ['_type', '_original', 'citation-key', 'type'])) {
                        $fields[$key] = $value;
                        $this->log?->debug("Overwriting field '$key' with '$value'");
                    } elseif ($key === '_type' && $value !== self::NO_OVERRIDE) {
                        $this->mapping->type = $value;
                        $this->log?->debug("Overwriting entry type with '$value'");
                    } elseif ($key === 'citation-key' && $value !== self::NO_OVERRIDE) {
                        $this->config->mapping->key = $value;
                        $this->log?->debug("Overwriting citation key with '$value'");
                    } elseif ($key === 'type' and $value !== ($entries[0]['_type'] ?? '')) {
                        $fields[$key] = $value;
                        $this->log?->debug("Overwriting field '$key' with '$value'");
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
            return $this->getLiteral(new PT($definition));
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
                return preg_replace('|^https?://[^/]*/|', '', (string) $this->getLiteral(new PT($definition->properties[0])));
            case self::TYPE_URL:
                return $this->formatAll($definition->properties, null, true, $definition->reqNmsp ?? $definition->prefNmsp ?? null, isset($definition->reqNmsp));
            default:
                throw new RuntimeException('Unsupported property type ' . $definition->type, 500);
        }
    }

    private function formatKey(): string {
        $keyCfg = $this->config->mapping->key;
        if (is_object($keyCfg)) {
            $surname = new PT($this->config->mapping->person->surname);
            $actors  = [];
            foreach ($keyCfg->actors as $property) {
                $tmpl = new QT($this->node, $property);
                foreach ($this->meta->listObjects($tmpl) as $actor) {
                    $actors[] = $this->getLiteral($surname->withSubject($actor));
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
            $year = substr((string) $this->getLiteral(new PT($keyCfg->year)), 0, 4);
            $id   = preg_replace('|^.*/|', '', (string) $this->node);
            $key  = $actors . '_' . $year . '_' . $id;
        } else {
            $key = $keyCfg;
        }
        return preg_replace('/[^-a-zA-Z0-9_]/', '', $key);
    }

    private function formatPerson(TermInterface $person): string {
        $cfg     = $this->config->mapping->person;
        $tmpl    = new QT($person);
        $name    = $this->getLiteral($tmpl->withPredicate($cfg->name));
        $surname = $this->getLiteral($tmpl->withPredicate($cfg->surname));
        if (!empty($name) || !empty($surname)) {
            return "$surname, $name";
        } else {
            return '{' . $this->getLiteral($tmpl->withPredicate($cfg->label)) . '}';
        }
    }

    private function getLiteral(QT | PT $tmpl): ?string {
        if ($tmpl instanceof PT || $tmpl->getSubject() === null) {
            $tmpl = $tmpl->withSubject($this->node);
        }
        $tmplLang = $tmpl->withObject(new LT(null, LT::ANY, $this->lang));
        $tmplUnd  = $tmpl->withObject(new LT(null, LT::ANY, 'und'));
        $tmplAny  = $tmpl->withObject(new LT(null, LT::ANY));
        $value    = $this->meta->getObject($tmplLang) ?? ($this->meta->getObject($tmplUnd) ?? $this->meta->getObject($tmplAny));
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
     * @param array<string> $properties
     * @param TermInterface|null $resource
     * @param bool $onlyUrl
     * @param string | null $nmsp
     * @param bool $reqNmsp
     * @return string
     */
    private function formatAll(array $properties,
                               TermInterface | null $resource = null,
                               bool $onlyUrl = false, ?string $nmsp = null,
                               bool $reqNmsp = false): string {
        $tmpl      = new QT($resource ?? $this->node);
        $literals  = [];
        $resources = [];
        $values    = [];
        foreach ($properties as $property) {
            foreach ($this->meta->listObjects($tmpl->withPredicate($property)) as $i) {
                if ($i instanceof LiteralInterface) {
                    $value = (string) $i;
                    if (!empty($value)) {
                        $lang = (string) $i->getLang();
                        if (!isset($literals[$lang])) {
                            $literals[$lang] = [];
                        }
                        $values[$lang][] = strpos($value, ',') !== false ? '{' . $value . '}' : $value;
                    }
                } else {
                    if ($onlyUrl) {
                        $value = (string) $i;
                    } else {
                        $value = $this->getLiteral(new QT($i, $this->schema->label));
                    }
                    if (!empty($value)) {
                        $resources[] = strpos($value, ',') !== false ? '{' . $value . '}' : $value;
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
            return count($values) > 0 && !$reqNmsp ? $values[0] : '';
        }
        sort($values);
        return join(', ', $values);
    }

    /**
     * 
     * @param array<string> $properties
     * @param TermInterface|string|null $resource
     * @return string
     */
    private function formatPersons(array $properties,
                                   TermInterface | string | null $resource = null): string {
        $tmpl    = new QT($resource ?? $this->node);
        $persons = [];
        foreach ($properties as $property) {
            foreach ($this->meta->listObjects($tmpl->withPredicate($property)) as $person) {
                $pid = (string) $person;
                if (!isset($persons[$pid])) {
                    $persons[$pid] = $this->formatPerson($person);
                }
            }
        }
        return join(' and ', $persons);
    }

    private function formatParent(string $type, string $property): ?string {
        $continue = true;
        $tmpl     = new QT($this->node, $this->schema->parent);
        while ($continue && ($parent   = $this->meta->getObject($tmpl))) {
            $tmpl     = $tmpl->withSubject($parent);
            $continue &= $type === self::SRC_TOP_COLLECTION;
        }
        if ($this->node->equals($tmpl->getSubject())) {
            return null;
        }
        $biblatexRes       = clone $this;
        $biblatexRes->node = $tmpl->getSubject();
        $biblatex          = $biblatexRes->getBiblatex($this->lang, null, $property);
        return empty($biblatex) ? null : $biblatex;
    }

    private function escapeBiblatex(string $value): string {
        return $value; // it seems that most important clients, like citation.js, anyway don't support any form of escaping
    }
}
